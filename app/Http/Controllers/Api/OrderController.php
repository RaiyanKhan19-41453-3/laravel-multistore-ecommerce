<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PhoneHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items', 'payments'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->with(['items.product', 'items.productVariant', 'payments', 'coupon', 'shipments'])
            ->findOrFail($order);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        try {
            $this->orderService->cancel($order, $request->input('reason'));

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'nullable|email|max:255|required_without:phone',
            'phone' => 'nullable|string|max:20|required_without:email',
            'order_number' => 'required|string|max:50',
        ]);

        $email = ! empty($validated['email']) ? strtolower(trim($validated['email'])) : null;
        $phone = PhoneHelper::normalize($validated['phone'] ?? null);

        if (! $email && ! $phone) {
            return response()->json([
                'success' => false,
                'message' => 'We couldn\'t find an order matching those details.',
            ], 404);
        }

        $query = Order::with(['items.product', 'items.productVariant', 'payments', 'coupon', 'shipments'])
            ->where('order_number', $validated['order_number']);

        $query->where(function ($q) use ($email, $phone) {
            if ($email) {
                $q->orWhere('guest_email', $email);
            }
            if ($phone) {
                $q->orWhere('guest_phone', $phone)
                    ->orWhere('shipping_phone', $phone);
            }
        });

        $order = $query->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'We couldn\'t find an order matching those details.',
            ], 404);
        }

        $discounts = $order->appliedDiscounts()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
            'type' => $d->type,
            'value' => $d->value,
        ]);

        return response()->json([
            'success' => true,
            'data' => array_merge($order->toArray(), [
                'applied_discounts' => $discounts,
            ]),
        ]);
    }
}
