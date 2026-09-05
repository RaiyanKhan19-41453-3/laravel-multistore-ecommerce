<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected DiscountService $discountService,
        protected ShippingService $shippingService,
    ) {}

    public function createFromCart(Cart $cart, ?User $user, array $shippingData, string $paymentMethod): Order
    {
        return DB::transaction(function () use ($cart, $user, $shippingData, $paymentMethod) {
            $cart->load(['items.product', 'items.productVariant', 'coupon.discount']);

            if ($cart->items->isEmpty()) {
                throw new \InvalidArgumentException('Cart is empty.');
            }

            $this->validateCartItems($cart);

            $subtotal = 0;
            $orderItems = [];

            foreach ($cart->items as $item) {
                $unitPrice = $item->getUnitPrice();
                $itemSubtotal = round($unitPrice * $item->quantity, 2);

                $orderItems[] = [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'name' => $item->productVariant?->name ?? $item->product->name,
                    'sku' => $item->productVariant?->sku ?? $item->product->sku,
                    'unit_price' => $unitPrice,
                    'quantity' => $item->quantity,
                    'subtotal' => $itemSubtotal,
                    'discount_amount' => 0,
                    'total' => $itemSubtotal,
                ];

                $subtotal += $itemSubtotal;
            }

            $productIds = $cart->items->pluck('product_id')->unique()->toArray();
            $variantIds = $cart->items->pluck('product_variant_id')->filter()->unique()->toArray();
            $couponCode = $cart->coupon?->code;

            $productTotals = [];
            $items = [];

            foreach ($cart->items as $item) {
                $unitPrice = $item->getUnitPrice();
                $lineTotal = $unitPrice * $item->quantity;
                $productTotals[$item->product_id] = ($productTotals[$item->product_id] ?? 0) + $lineTotal;

                $items[] = [
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
                $items,
            );

            $discountTotal = 0;
            $discountIds = [];

            if ($discountResult) {
                $discountTotal = ($discountResult['stacked'] ?? false)
                    ? $discountResult['total_amount']
                    : $discountResult['amount'];

                $appliedDiscounts = ($discountResult['stacked'] ?? false)
                    ? $discountResult['discounts']
                    : [['discount' => $discountResult['discount']]];

                $discountIds = array_map(
                    fn ($d) => $d['discount']->id,
                    $appliedDiscounts,
                );
            }

            $discountTotal = min($discountTotal, $subtotal);

            $discountedSubtotal = round($subtotal - $discountTotal, 2);

            $shippingCost = 0;
            $shippingMethodId = null;
            $shippingMethodName = null;
            $shippingEstimatedDays = null;

            if (! empty($shippingData['shipping_rate_id'])) {
                $rate = $this->shippingService->validateAndGetRate(
                    (int) $shippingData['shipping_rate_id'],
                    $shippingData['shipping_city']
                );

                $shippingCost = $this->shippingService->calculateShippingCost($rate, $discountedSubtotal);
                $shippingMethodId = $rate->shipping_method_id;
                $shippingMethodName = $rate->shippingMethod->name;
                $shippingEstimatedDays = $rate->shippingMethod->estimated_days;
            }

            $total = round($discountedSubtotal + $shippingCost, 2);

            $order = Order::create([
                'user_id' => $user?->id,
                'guest_email' => $user ? null : ($shippingData['guest_email'] ?? null),
                'guest_phone' => $user ? null : ($shippingData['phone'] ?? null),
                'status' => $paymentMethod === 'cod' ? 'confirmed' : 'pending',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'shipping_cost' => $shippingCost,
                'tax_amount' => 0,
                'total' => $total,
                'coupon_id' => $cart->coupon_id,
                'coupon_code' => $couponCode,
                'shipping_method_id' => $shippingMethodId,
                'shipping_method_name' => $shippingMethodName,
                'shipping_estimated_days' => $shippingEstimatedDays,
                'discount_ids' => $discountIds ?: null,
                'shipping_name' => $shippingData['shipping_name'],
                'shipping_phone' => $shippingData['delivery_phone'] ?? $shippingData['phone'] ?? null,
                'shipping_address' => $shippingData['shipping_address'],
                'shipping_city' => $shippingData['shipping_city'],
                'shipping_state' => $shippingData['shipping_state'] ?? null,
                'shipping_postal_code' => $shippingData['shipping_postal_code'] ?? null,
                'shipping_country' => $shippingData['shipping_country'] ?? 'Bangladesh',
                'notes' => $shippingData['notes'] ?? null,
                'expires_at' => $this->getExpirationTime($paymentMethod),
            ]);

            foreach ($orderItems as $orderItemData) {
                $order->items()->create($orderItemData);
            }

            $this->reserveInventoryForOrder($order);
            $this->markCartConverted($cart);

            if ($paymentMethod === 'cod') {
                $this->confirmCodOrder($order);
            }

            return $order->fresh(['items', 'payments', 'user', 'coupon']);
        });
    }

    public function confirmPayment(Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->find($order->id);

            if (! $order || $order->status !== 'pending') {
                return;
            }

            $order->update([
                'status' => 'confirmed',
                'paid_at' => now(),
            ]);

            $this->deductInventoryForOrder($order);

            if ($order->coupon_id && $order->coupon) {
                $this->discountService->incrementUsage($order->coupon->discount);
                $this->discountService->incrementCouponUsage($order->coupon);
            }

            $this->incrementAutomaticDiscountUsage($order);
        });
    }

    public function confirmCodOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('items');

            $this->deductInventoryForOrder($order);

            if ($order->coupon_id && $order->coupon) {
                $this->discountService->incrementUsage($order->coupon->discount);
                $this->discountService->incrementCouponUsage($order->coupon);
            }

            $this->incrementAutomaticDiscountUsage($order);
        });
    }

    public function updateStatus(Order $order, string $status): void
    {
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled', 'expired'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => ['completed'],
        ];

        $allowed = $validTransitions[$order->status] ?? [];

        if (! in_array($status, $allowed)) {
            throw new \InvalidArgumentException(
                "Cannot transition from '{$order->status}' to '{$status}'."
            );
        }

        $updateData = ['status' => $status];

        match ($status) {
            'shipped' => $updateData['shipped_at'] = now(),
            'delivered' => $updateData['delivered_at'] = now(),
            'cancelled' => $updateData['cancelled_at'] = now(),
            default => null,
        };

        $order->update($updateData);
    }

    public function cancel(Order $order, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $reason) {
            $originalStatus = $order->status;

            $this->updateStatus($order, 'cancelled');

            $order->update(['cancellation_reason' => $reason]);

            if ($originalStatus === 'confirmed') {
                $this->restoreInventoryForOrder($order);
            } elseif ($originalStatus === 'pending') {
                $this->releaseInventoryForOrder($order);
            }

            if ($originalStatus === 'confirmed') {
                if ($order->coupon_id && $order->coupon) {
                    $this->discountService->decrementUsage($order->coupon->discount);
                    $this->discountService->decrementCouponUsage($order->coupon);
                }

                $this->decrementAutomaticDiscountUsage($order);
            }
        });
    }

    public function expireOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->update(['status' => 'expired']);
            $this->releaseInventoryForOrder($order);
        });
    }

    public function handlePaymentFailure(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->update(['status' => 'cancelled']);
            $this->releaseInventoryForOrder($order);
        });
    }

    public function createPaymentForOrder(Order $order, string $paymentMethod): Payment
    {
        return $order->payments()->create([
            'method' => $paymentMethod,
            'status' => 'pending',
            'amount' => $order->total,
            'gateway' => config('payment.default', 'sslcommerz'),
        ]);
    }

    private function validateCartItems(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->is_active) {
                throw new \InvalidArgumentException("Product '{$item->product_id}' is no longer available.");
            }

            if ($item->productVariant && ! $item->productVariant->is_active) {
                throw new \InvalidArgumentException("Variant for product '{$product->name}' is no longer available.");
            }

            $inventory = $this->getInventoryForItem($item);
            $available = $inventory->getAvailableQuantity();

            if ($available < $item->quantity) {
                throw new \InvalidArgumentException(
                    "Insufficient stock for '{$product->name}'. Available: {$available}, requested: {$item->quantity}."
                );
            }
        }
    }

    private function reserveInventoryForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            $inventory = $this->getInventoryForOrderItem($item);
            $this->inventoryService->reserve($inventory, $item->quantity);
        }
    }

    private function deductInventoryForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            $inventory = Inventory::lockForUpdate()
                ->where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->first();

            if (! $inventory) {
                Log::warning('Inventory not found during order deduction', [
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                ]);

                continue;
            }

            $inventory->decrement('reserved_quantity', $item->quantity);
            $inventory->decrement('quantity', $item->quantity);

            InventoryMovement::create([
                'inventory_id' => $inventory->id,
                'type' => 'sale',
                'quantity' => -$item->quantity,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'note' => 'Order #'.$order->order_number,
            ]);
        }
    }

    private function releaseInventoryForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            $inventory = $this->getInventoryForOrderItem($item);
            $this->inventoryService->release($inventory, $item->quantity);
        }
    }

    private function restoreInventoryForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            $inventory = Inventory::lockForUpdate()
                ->where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->first();

            if (! $inventory) {
                Log::warning('Inventory not found during order cancellation restore', [
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                ]);

                continue;
            }

            $inventory->increment('quantity', $item->quantity);
            $this->inventoryService->release($inventory, $item->quantity);

            InventoryMovement::create([
                'inventory_id' => $inventory->id,
                'type' => 'return',
                'quantity' => $item->quantity,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'note' => 'Order #'.$order->order_number.' cancelled',
            ]);
        }
    }

    private function getInventoryForItem(CartItem $item): Inventory
    {
        if ($item->productVariant) {
            return $this->inventoryService->getForVariant($item->productVariant)
                ?? throw new \InvalidArgumentException('No inventory found for this variant.');
        }

        $inventories = $this->inventoryService->getForProduct($item->product);

        return $inventories->first()
            ?? throw new \InvalidArgumentException('No inventory found for this product.');
    }

    private function getInventoryForOrderItem(OrderItem $item): Inventory
    {
        if ($item->product_variant_id) {
            return Inventory::where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->first()
                ?? throw new \InvalidArgumentException('No inventory found for this order item.');
        }

        return Inventory::where('product_id', $item->product_id)
            ->whereNull('product_variant_id')
            ->first()
            ?? throw new \InvalidArgumentException('No inventory found for this order item.');
    }

    private function markCartConverted(Cart $cart): void
    {
        $cart->load('items');
        $cart->items()->delete();
        $cart->update(['status' => 'converted']);
    }

    private function getExpirationTime(?string $paymentMethod): ?Carbon
    {
        if ($paymentMethod === 'cod') {
            return null;
        }

        $ttlMinutes = config('payment.payment_method_ttl.'.$paymentMethod)
            ?? config('payment.reservation_ttl_minutes', 15);

        if ($ttlMinutes === null) {
            return null;
        }

        return now()->addMinutes($ttlMinutes);
    }

    private function incrementAutomaticDiscountUsage(Order $order): void
    {
        if (empty($order->discount_ids)) {
            return;
        }

        foreach ($order->discount_ids as $discountId) {
            if ($order->coupon_id && $discountId === $order->coupon->discount_id) {
                continue;
            }

            $discount = Discount::find($discountId);
            if ($discount) {
                $this->discountService->incrementUsage($discount);
            }
        }
    }

    private function decrementAutomaticDiscountUsage(Order $order): void
    {
        if (empty($order->discount_ids)) {
            return;
        }

        foreach ($order->discount_ids as $discountId) {
            if ($order->coupon_id && $discountId === $order->coupon->discount_id) {
                continue;
            }

            $discount = Discount::find($discountId);
            if ($discount) {
                $this->discountService->decrementUsage($discount);
            }
        }
    }
}
