<?php

namespace App\Http\Controllers\Api;

use App\Helpers\BearerUser;
use App\Helpers\PhoneHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentRetryController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    /**
     * Re-initiate payment on an existing pending order. The cart stays
     * converted: retrying never rebuilds it and never creates a second
     * order. Pass the same Idempotency-Key to safely replay a click.
     */
    public function store(Request $request, string $order_number): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = BearerUser::fromRequest($request);

        $order = Order::with('payments')->where('order_number', $order_number)->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        if ($user) {
            if ($order->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }
        } else {
            $email = ! empty($validated['email']) ? strtolower(trim($validated['email'])) : null;
            $phone = PhoneHelper::normalize($validated['phone'] ?? null);

            $matches = ($email && $order->guest_email && strtolower(trim($order->guest_email)) === $email)
                || ($phone && in_array($phone, [$order->guest_phone, $order->shipping_phone], true));

            if (! $matches) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                ], 404);
            }
        }

        $key = $request->header('Idempotency-Key') ?: (string) Str::uuid();

        if (strlen($key) > 64) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key must not exceed 64 characters.',
            ], 422);
        }

        try {
            $result = $this->paymentService->retryPayment($order, $key);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() === 409 ? 409 : 502);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $result['order']->order_number,
                'status' => $result['order']->status,
                'payment' => [
                    'id' => $result['payment']->id,
                    'redirect_url' => $result['redirect_url'],
                    'replayed' => $result['replayed'],
                ],
            ],
        ]);
    }
}
