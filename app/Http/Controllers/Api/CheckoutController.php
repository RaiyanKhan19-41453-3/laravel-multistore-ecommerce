<?php

namespace App\Http\Controllers\Api;

use App\Helpers\BearerUser;
use App\Helpers\PhoneHelper;
use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $bearerUser = BearerUser::fromRequest($request);
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
            'payment_method' => 'required|string|in:'.implode(',', PaymentGatewayFactory::getEnabled()),
            'shipping_rate_id' => 'required|integer|exists:shipping_rates,id',
            'notes' => 'nullable|string|max:500',
            'guest_email' => $isGuest ? 'required|email|max:255' : 'nullable|email|max:255',
        ]);

        $validated['phone'] = PhoneHelper::normalize(($validated['phone'] ?? null) ?: $bearerUser?->phone);
        $validated['delivery_phone'] = PhoneHelper::normalize(($validated['delivery_phone'] ?? null) ?: null);

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
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // From here the order exists and the cart is converted: failures
        // below must carry the order_number so the storefront can offer
        // retry-payment on the same order instead of a dead end.
        try {
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
                'data' => [
                    'order' => [
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                    ],
                ],
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'order' => [
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                    ],
                ],
            ], 502);
        }
    }
}
