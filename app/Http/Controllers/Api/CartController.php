<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $request->attributes->get('cart');

        if (! $cart) {
            return response()->json([
                'success' => true,
                'data' => $this->emptyCart(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->cartService->getCartSummary($cart),
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $cart = $request->attributes->get('cart');

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart could not be resolved.',
            ], 400);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $variant = isset($validated['product_variant_id'])
            ? ProductVariant::findOrFail($validated['product_variant_id'])
            : null;

        try {
            $this->cartService->addItem($cart, $product, $variant, $validated['quantity']);

            return response()->json([
                'success' => true,
                'data' => $this->cartService->getCartSummary($cart->fresh('items')),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function updateQuantity(Request $request, int $cartItemId): JsonResponse
    {
        $cart = $request->attributes->get('cart');

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart could not be resolved.',
            ], 400);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $item = $cart->items()->findOrFail($cartItemId);

        try {
            $this->cartService->updateQuantity($item, $validated['quantity']);

            return response()->json([
                'success' => true,
                'data' => $this->cartService->getCartSummary($cart->fresh('items')),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function removeItem(Request $request, int $cartItemId): JsonResponse
    {
        $cart = $request->attributes->get('cart');

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart could not be resolved.',
            ], 400);
        }

        $item = $cart->items()->findOrFail($cartItemId);

        $this->cartService->removeItem($item);

        return response()->json([
            'success' => true,
            'data' => $this->cartService->getCartSummary($cart->fresh('items')),
        ]);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $cart = $request->attributes->get('cart');

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart could not be resolved.',
            ], 400);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        try {
            $coupon = $this->cartService->applyCoupon($cart, $validated['code']);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if (! $coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive coupon code.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->cartService->getCartSummary($cart->fresh('items')),
        ]);
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $request->attributes->get('cart');

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart could not be resolved.',
            ], 400);
        }

        $this->cartService->removeCoupon($cart);

        return response()->json([
            'success' => true,
            'data' => $this->cartService->getCartSummary($cart->fresh('items')),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $request->attributes->get('cart');

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart could not be resolved.',
            ], 400);
        }

        $this->cartService->clearCart($cart);

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared.',
            'data' => $this->emptyCart(),
        ]);
    }

    public function merge(Request $request): JsonResponse
    {
        $guestToken = $request->input('guest_token');

        if (! $guestToken) {
            return response()->json([
                'success' => false,
                'message' => 'Guest token is required.',
            ], 422);
        }

        $this->cartService->mergeGuestCart($request->user(), $guestToken);

        $cart = $this->cartService->getOrCreateForUser($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Guest cart merged successfully.',
            'data' => $this->cartService->getCartSummary($cart),
        ]);
    }

    private function emptyCart(): array
    {
        return [
            'items' => [],
            'subtotal' => 0,
            'discount_total' => 0,
            'total' => 0,
            'item_count' => 0,
            'coupon_code' => null,
            'discount_details' => null,
        ];
    }
}
