<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PhoneHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CheckoutController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $bearerUser = $this->resolveBearerUser($request);
        $isGuest = $bearerUser === null;

        $validated = $request->validate([
            'shipping_name' => 'required|string|max:255',
            'phone' => $isGuest ? 'required|string|max:20' : 'nullable|string|max:20',
            'delivery_phone' => 'nullable|string|max:20',
            'shipping_address' => 'required|string',
            'shipping_city' => 'required|string|max:100',
            'shipping_state' => 'nullable|string|max:100',
            'shipping_postal_code' => 'nullable|string|max:20',
            'shipping_country' => 'nullable|string|max:100',
            'payment_method' => 'required|string|in:bkash,nagad,rocket,card,cod',
            'shipping_rate_id' => 'required|integer|exists:shipping_rates,id',
            'notes' => 'nullable|string|max:500',
            'guest_email' => $isGuest ? 'required|email|max:255' : 'nullable|email|max:255',
        ]);

        $validated['phone'] = PhoneHelper::normalize($validated['phone'] ?? $bearerUser?->phone ?? null);
        $validated['delivery_phone'] = PhoneHelper::normalize($validated['delivery_phone'] ?? null);

        $cart = $request->attributes->get('cart');

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart could not be resolved.',
            ], 400);
        }

        $cart->load('items');

        if ($cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty.',
            ], 422);
        }

        if (! $isGuest && $cart->user_id && $cart->user_id !== $bearerUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        try {
            $order = $this->orderService->createFromCart(
                $cart,
                $bearerUser,
                $validated,
                $validated['payment_method']
            );

            $response = [
                'success' => true,
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'total' => $order->total,
                        'guest_email' => $order->guest_email,
                        'items' => $order->items->map(fn ($item) => [
                            'name' => $item->name,
                            'sku' => $item->sku,
                            'unit_price' => $item->unit_price,
                            'quantity' => $item->quantity,
                            'subtotal' => $item->subtotal,
                            'discount_amount' => $item->discount_amount,
                            'total' => $item->total,
                        ]),
                    ],
                ],
            ];

            if ($validated['payment_method'] !== 'cod') {
                $payment = $this->orderService->createPaymentForOrder($order, $validated['payment_method']);
                $paymentResult = $this->paymentService->initiate($order, $payment, $validated['payment_method']);

                $response['data']['payment'] = [
                    'id' => $paymentResult['payment_id'],
                    'redirect_url' => $paymentResult['redirect_url'],
                ];
            } else {
                $response['data']['message'] = 'Order confirmed. Pay on delivery.';
            }

            return response()->json($response);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    private function resolveBearerUser(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return null;
        }

        $user = $accessToken->tokenable;

        return $user instanceof User ? $user : null;
    }
}
