<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

class CartService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected DiscountService $discountService,
    ) {}

    public function getOrCreateForUser(User $user): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'active'],
            ['status' => 'active', 'expires_at' => now()->addDays(30)]
        );
    }

    public function getOrCreateForGuest(string $guestToken): Cart
    {
        $existing = Cart::where('guest_token', $guestToken)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return $existing;
        }

        $cleared = Cart::where('guest_token', $guestToken)
            ->whereIn('status', ['expired', 'abandoned'])
            ->first();

        if ($cleared) {
            $cleared->update(['status' => 'active', 'expires_at' => now()->addDays(30)]);

            return $cleared;
        }

        return Cart::create([
            'guest_token' => $guestToken,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function addItem(Cart $cart, Product $product, ?ProductVariant $variant, int $quantity): Cart
    {
        $this->validateProduct($product, $variant);

        $existingItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        if ($existingItem) {
            $this->updateQuantity($existingItem, $existingItem->quantity + $quantity);
        } else {
            $inventory = $this->getInventory($product, $variant);
            $this->inventoryService->reserve($inventory, $quantity);

            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $quantity,
            ]);
        }

        return $cart->fresh('items');
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        $inventory = $this->getInventory($item->product, $item->productVariant);
        $delta = $quantity - $item->quantity;

        if ($delta > 0) {
            $this->inventoryService->reserve($inventory, $delta);
        } elseif ($delta < 0) {
            $this->inventoryService->release($inventory, abs($delta));
        }

        $item->update(['quantity' => $quantity]);
    }

    public function removeItem(CartItem $item): void
    {
        $inventory = $this->getInventory($item->product, $item->productVariant);
        $this->inventoryService->release($inventory, $item->quantity);

        $item->delete();
    }

    public function clearCart(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            $this->removeItem($item);
        }
    }

    public function mergeGuestCart(User $user, string $guestToken): void
    {
        $guestCart = Cart::where('guest_token', $guestToken)
            ->where('status', 'active')
            ->first();

        if (! $guestCart || $guestCart->items->isEmpty()) {
            return;
        }

        $userCart = $this->getOrCreateForUser($user);

        foreach ($guestCart->items as $guestItem) {
            $existingItem = $userCart->items()
                ->where('product_id', $guestItem->product_id)
                ->where('product_variant_id', $guestItem->product_variant_id)
                ->first();

            if ($existingItem) {
                $newQuantity = $existingItem->quantity + $guestItem->quantity;

                try {
                    $this->releaseGuestItemReservation($guestItem);
                    $this->updateQuantity($existingItem, $newQuantity);
                    $guestItem->delete();
                } catch (\InvalidArgumentException) {
                    $this->releaseGuestItemReservation($guestItem);
                    $guestItem->delete();
                }
            } else {
                try {
                    $guestItem->update(['cart_id' => $userCart->id]);
                } catch (\InvalidArgumentException) {
                    $guestItem->delete();
                }
            }
        }

        if ($guestCart->coupon_id && ! $userCart->coupon_id) {
            $userCart->update(['coupon_id' => $guestCart->coupon_id]);
        }

        $guestCart->update(['status' => 'merged']);
    }

    public function applyCoupon(Cart $cart, string $code): ?Coupon
    {
        $coupon = $this->discountService->applyCoupon($code);

        if (! $coupon) {
            return null;
        }

        if ($cart->user_id) {
            $identifier = $this->discountService->redemptionIdentifier($cart->user, null);
            $this->discountService->assertCouponRedeemable($coupon, $identifier);
        }

        $cart->update(['coupon_id' => $coupon->id]);

        return $coupon;
    }

    public function removeCoupon(Cart $cart): void
    {
        $cart->update(['coupon_id' => null]);
    }

    public function getCartSummary(Cart $cart): array
    {
        $cart->load(['items.product.images', 'items.productVariant', 'coupon.discount']);

        $subtotal = 0;
        $items = [];

        foreach ($cart->items as $item) {
            $unitPrice = $item->getUnitPrice();
            $lineTotal = $unitPrice * $item->quantity;
            $subtotal += $lineTotal;

            $image = $item->product->images->firstWhere('is_primary', true)
                ?? $item->product->images->first();

            $items[] = [
                'id' => $item->id,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'sku' => $item->product->sku,
                ],
                'product_variant' => $item->productVariant ? [
                    'id' => $item->productVariant->id,
                    'name' => $item->productVariant->name,
                    'sku' => $item->productVariant->sku,
                ] : null,
                'quantity' => $item->quantity,
                'unit_price' => round($unitPrice, 2),
                'line_total' => round($lineTotal, 2),
                'image' => $image?->getUrl('thumbnail') ?? $image?->getUrl(),
            ];
        }

        $productIds = $cart->items->pluck('product_id')->unique()->toArray();
        $variantIds = $cart->items->pluck('product_variant_id')->filter()->unique()->toArray();
        $couponCode = $cart->coupon?->code;

        $productTotals = [];
        $discountItems = [];

        foreach ($cart->items as $item) {
            $unitPrice = $item->getUnitPrice();
            $lineTotal = $unitPrice * $item->quantity;
            $productTotals[$item->product_id] = ($productTotals[$item->product_id] ?? 0) + $lineTotal;

            $discountItems[] = [
                'cart_item_id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->product_variant_id,
                'total' => $lineTotal,
            ];
        }

        $discountResult = $this->discountService->bestDiscountForOrder(
            $subtotal,
            $couponCode,
            $productIds,
            $productTotals,
            $variantIds,
            $discountItems,
            $cart->user_id ? $this->discountService->redemptionIdentifier($cart->user, null) : null,
        );

        $discountTotal = 0;
        $discountDetails = null;
        $itemDiscountsMap = [];

        if ($discountResult) {
            $discounts = isset($discountResult['stacked'])
                ? $discountResult['discounts']
                : [['discount' => $discountResult['discount'], 'amount' => $discountResult['amount']]];

            $discountTotal = isset($discountResult['stacked'])
                ? $discountResult['total_amount']
                : $discountResult['amount'];

            $reducedTotals = $productTotals;
            $itemIdToProductId = collect($discountItems)->pluck('product_id', 'cart_item_id');

            foreach ($discounts as $d) {
                $perItem = $this->discountService->getPerItemDiscountAmounts(
                    $d['discount'],
                    $productIds,
                    $reducedTotals,
                    $variantIds,
                    $discountItems,
                );

                foreach ($perItem as $key => $info) {
                    $itemDiscountsMap[$key][] = $info;
                    $pid = $itemIdToProductId[$key] ?? $key;
                    $reducedTotals[$pid] = max(0, ($reducedTotals[$pid] ?? 0) - $info['amount']);
                }
            }

            $discountDetails = isset($discountResult['stacked'])
                ? array_map(fn ($d) => [
                    'id' => $d['discount']->id,
                    'name' => $d['discount']->name,
                    'type' => $d['discount']->type,
                    'value' => (float) $d['discount']->value,
                    'amount' => round($d['amount'], 2),
                    'level' => $this->discountService->getDiscountLevel($d['discount']),
                    'target' => $this->discountService->getDiscountTarget($d['discount']),
                ], $discountResult['discounts'])
                : [
                    'id' => $discountResult['discount']->id,
                    'name' => $discountResult['discount']->name,
                    'type' => $discountResult['discount']->type,
                    'value' => (float) $discountResult['discount']->value,
                    'amount' => round($discountResult['amount'], 2),
                    'level' => $this->discountService->getDiscountLevel($discountResult['discount']),
                    'target' => $this->discountService->getDiscountTarget($discountResult['discount']),
                ];
        }

        foreach ($items as &$item) {
            $item['item_discounts'] = $itemDiscountsMap[$item['id']] ?? [];
        }
        unset($item);

        return [
            'id' => $cart->id,
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'total' => round(max(0, $subtotal - $discountTotal), 2),
            'item_count' => $cart->getItemCount(),
            'coupon_code' => $couponCode,
            'discount_details' => $discountDetails,
        ];
    }

    private function validateProduct(Product $product, ?ProductVariant $variant): void
    {
        if (! $product->is_active) {
            throw new \InvalidArgumentException('Product is not available.');
        }

        if ($variant && ! $variant->is_active) {
            throw new \InvalidArgumentException('Product variant is not available.');
        }

        if ($variant && $variant->product_id !== $product->id) {
            throw new \InvalidArgumentException('Product variant does not belong to this product.');
        }

        if ($product->isVariable() && ! $variant) {
            throw new \InvalidArgumentException('This product requires a variant selection.');
        }

        if (! $product->isVariable() && $variant) {
            throw new \InvalidArgumentException('Simple products cannot have a variant.');
        }
    }

    private function releaseGuestItemReservation(CartItem $guestItem): void
    {
        $guestItem->loadMissing(['product', 'productVariant']);

        try {
            $inventory = $this->getInventory($guestItem->product, $guestItem->productVariant);
            $this->inventoryService->release($inventory, $guestItem->quantity);
        } catch (\InvalidArgumentException) {
            // Inventory row gone — nothing left to release.
        }
    }

    private function getInventory(Product $product, ?ProductVariant $variant)
    {
        if ($variant) {
            return $this->inventoryService->getForVariant($variant)
                ?? throw new \InvalidArgumentException('No inventory found for this variant.');
        }

        $inventories = $this->inventoryService->getForProduct($product);

        return $inventories->first()
            ?? throw new \InvalidArgumentException('No inventory found for this product.');
    }
}
