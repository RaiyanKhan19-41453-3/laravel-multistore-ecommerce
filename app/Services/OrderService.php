<?php

namespace App\Services;

use App\Jobs\SubmitZatcaDocument;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Models\ZatcaDocument;
use App\Services\Zatca\ZatcaDocumentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected DiscountService $discountService,
        protected ShippingService $shippingService,
        protected TaxService $taxService,
        protected ZatcaDocumentService $zatcaDocuments,
        protected NotificationService $notifications = new NotificationService,
        protected PaymentService $payments = new PaymentService,
    ) {}

    public function createFromCart(Cart $cart, ?User $user, array $shippingData, string $paymentMethod): Order
    {
        $order = DB::transaction(function () use ($cart, $user, $shippingData, $paymentMethod) {
            // Lock the cart first: racing checkouts (double-click, retry
            // after timeout) serialize here, and the loser sees a converted
            // cart instead of creating a duplicate order.
            $cart = Cart::lockForUpdate()->findOrFail($cart->id);

            if ($cart->status !== 'active') {
                throw new \InvalidArgumentException('This cart has already been checked out.');
            }

            $cart->load(['items.product.categories', 'items.productVariant', 'coupon.discount']);

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
                    'store_id' => $cart->store_id,
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
                $items,
                $this->discountService->redemptionIdentifier($user, $shippingData['guest_email'] ?? null),
                $cart->store_id,
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

                // Re-check global usage limits under row locks inside the
                // order transaction. The quote-time filter can go stale
                // between concurrent checkouts; without this, a limited
                // promotion could be granted more times than allowed.
                foreach ($appliedDiscounts as $applied) {
                    $this->discountService->assertDiscountRedeemable($applied['discount']);
                }
            }

            $discountTotal = min($discountTotal, $subtotal);

            // Allocate the granted discount across lines pro-rata (last line
            // absorbs the rounding penny) so item totals reconcile to the
            // discounted subtotal instead of staying gross.
            $allocatedDiscount = 0.0;
            $orderItemCount = count($orderItems);

            foreach ($orderItems as $index => $line) {
                $lineDiscount = $subtotal > 0
                    ? round($discountTotal * ($line['subtotal'] / $subtotal), 2)
                    : 0.0;

                if ($index === $orderItemCount - 1) {
                    $lineDiscount = round($discountTotal - $allocatedDiscount, 2);
                }

                $allocatedDiscount += $lineDiscount;
                $orderItems[$index]['discount_amount'] = $lineDiscount;
                $orderItems[$index]['total'] = round($line['subtotal'] - $lineDiscount, 2);
            }

            $discountedSubtotal = round($subtotal - $discountTotal, 2);

            $shippingCost = 0;
            $shippingMethodId = null;
            $shippingMethodName = null;
            $shippingEstimatedDays = null;

            if (! empty($shippingData['shipping_rate_id'])) {
                $rate = $this->shippingService->validateAndGetRate(
                    (int) $shippingData['shipping_rate_id'],
                    $shippingData['shipping_city'],
                    $shippingData['shipping_country'] ?? 'Bangladesh',
                    $cart->store_id,
                );

                $shippingCost = $this->shippingService->calculateShippingCost($rate, $discountedSubtotal);
                $shippingMethodId = $rate->shipping_method_id;
                $shippingMethodName = $rate->shippingMethod->name;
                $shippingEstimatedDays = $rate->shippingMethod->estimated_days;
            }

            $total = round($discountedSubtotal + $shippingCost, 2);

            $taxAmount = $this->calculateOrderTax($cart, $subtotal, $discountTotal, $shippingCost);
            $total = round($total + $taxAmount, 2);

            $order = Order::create([
                'store_id' => $cart->store_id,
                'user_id' => $user?->id,
                'guest_email' => $user ? null : ($shippingData['guest_email'] ?? null),
                'guest_phone' => $user ? null : ($shippingData['phone'] ?? null),
                'status' => $paymentMethod === 'cod' ? 'confirmed' : 'pending',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'coupon_id' => $cart->coupon_id,
                'coupon_code' => $couponCode,
                'shipping_method_id' => $shippingMethodId,
                'shipping_method_name' => $shippingMethodName,
                'shipping_estimated_days' => $shippingEstimatedDays,
                'discount_ids' => $discountIds ?: null,
                'shipping_name' => $shippingData['shipping_name'],
                'shipping_phone' => $shippingData['delivery_phone'] ?: $shippingData['phone'] ?? null,
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

            $couponIdentifier = $this->discountService->redemptionIdentifier(
                $user, $shippingData['guest_email'] ?? null
            );

            if ($cart->coupon) {
                $this->discountService->assertCouponRedeemable($cart->coupon, $couponIdentifier);
            }

            if ($cart->coupon && in_array($cart->coupon->discount_id, $discountIds)
                && $couponIdentifier !== null) {
                $this->discountService->reserveCouponRedemption($cart->coupon, $couponIdentifier, $order->id);
            }

            // Cart reservations transfer to the order: release the cart holds
            // first, then reserve fresh for the order. reserve() enforces true
            // availability atomically, so oversell is impossible.
            $this->releaseCartReservations($cart);
            $this->reserveInventoryForOrder($order);
            $this->markCartConverted($cart);

            if ($paymentMethod === 'cod') {
                $this->confirmCodOrder($order);
            }

            return $order->fresh(['items', 'payments', 'user', 'coupon']);
        });

        if ($order->status === 'confirmed') {
            $this->notifications->notifyOrderConfirmed($order);
        } else {
            $this->notifications->notifyOrderPlaced($order);
        }

        return $order;
    }

    public function confirmPayment(Order $order, Payment $payment): void
    {
        $confirmed = DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->find($order->id);

            if (! $order || $order->status !== 'pending') {
                return false;
            }

            $order->update([
                'status' => 'confirmed',
                'paid_at' => now(),
            ]);

            $this->deductInventoryForOrder($order);

            $couponDiscount = $order->coupon?->discount;

            if ($order->coupon_id && $couponDiscount
                && in_array($order->coupon?->discount_id, $order->discount_ids ?? [])) {
                $this->discountService->incrementUsage($couponDiscount);
                $this->discountService->incrementCouponUsage($order->coupon);
            }

            $this->incrementAutomaticDiscountUsage($order);
            $this->queueZatcaDocument($order);

            return true;
        });

        if ($confirmed) {
            $this->notifications->notifyOrderConfirmed($order->fresh() ?? $order);
        }
    }

    public function confirmCodOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('items');

            $this->deductInventoryForOrder($order);

            $couponDiscount = $order->coupon?->discount;

            if ($order->coupon_id && $couponDiscount
                && in_array($order->coupon?->discount_id, $order->discount_ids ?? [])) {
                $this->discountService->incrementUsage($couponDiscount);
                $this->discountService->incrementCouponUsage($order->coupon);
            }

            $this->incrementAutomaticDiscountUsage($order);
            $this->queueZatcaDocument($order);
        });
    }

    public function updateStatus(Order $order, string $status): void
    {
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled', 'expired'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered', 'cancelled'],
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

        match ($status) {
            'shipped' => $this->notifications->notifyOrderShipped($order->fresh() ?? $order),
            'delivered' => $this->notifications->notifyOrderDelivered($order->fresh() ?? $order),
            default => null,
        };
    }

    /**
     * Move a paid, packed order to shipped (courier pickup / in-transit
     * scans). Only confirmed/processing orders qualify: jumping from
     * pending would ship unpaid goods, and downgrading a delivered order
     * would corrupt history. Idempotent for redelivered scans.
     *
     * @throws \InvalidArgumentException
     */
    public function markShipped(Order $order): void
    {
        if ($order->status === 'shipped') {
            $order->update(['shipped_at' => $order->shipped_at ?? now()]);

            return;
        }

        if (! in_array($order->status, ['confirmed', 'processing'], true)) {
            throw new \InvalidArgumentException(
                "Cannot mark order as shipped from '{$order->status}'."
            );
        }

        $order->update(['status' => 'shipped', 'shipped_at' => now()]);
        $this->notifications->notifyOrderShipped($order->fresh() ?? $order);
    }

    /**
     * Move an order to delivered (courier delivery scan). Allowed from any
     * paid pipeline state; shipped_at is backfilled when the pickup scan
     * never arrived. Never from pending (unpaid), cancelled, expired, or
     * completed. Idempotent for redelivered scans.
     *
     * @throws \InvalidArgumentException
     */
    public function markDelivered(Order $order): void
    {
        if ($order->status === 'delivered') {
            return;
        }

        if (! in_array($order->status, ['confirmed', 'processing', 'shipped'], true)) {
            throw new \InvalidArgumentException(
                "Cannot mark order as delivered from '{$order->status}'."
            );
        }

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'shipped_at' => $order->shipped_at ?? now(),
        ]);
        $this->notifications->notifyOrderDelivered($order->fresh() ?? $order);
    }

    public function cancel(Order $order, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $originalStatus = $order->status;

            $this->updateStatus($order, 'cancelled');

            $order->update(['cancellation_reason' => $reason]);
            $this->discountService->releaseCouponRedemptionsForOrder($order);

            if ($originalStatus === 'confirmed' || $originalStatus === 'processing' || $originalStatus === 'shipped') {
                $this->restoreInventoryForOrder($order);
            } elseif ($originalStatus === 'pending') {
                $this->releaseInventoryForOrder($order);
            }

            if ($originalStatus === 'confirmed' || $originalStatus === 'processing' || $originalStatus === 'shipped') {
                $couponDiscount = $order->coupon?->discount;

                if ($order->coupon_id && $couponDiscount
                    && in_array($order->coupon?->discount_id, $order->discount_ids ?? [])) {
                    $this->discountService->decrementUsage($couponDiscount);
                    $this->discountService->decrementCouponUsage($order->coupon);
                }

                $this->decrementAutomaticDiscountUsage($order);
            }
        });

        $this->refundPaidPayments($order);

        $this->notifications->notifyOrderCancelled($order->fresh() ?? $order, $reason);
    }

    /**
     * Return captured money for every paid payment on a cancelled order.
     * Runs after the cancel transaction commits so gateway latency never
     * holds row locks; a failed refund never blocks the cancellation —
     * it is logged for manual review instead.
     */
    private function refundPaidPayments(Order $order): void
    {
        $paidPayments = $order->payments()->where('status', 'paid')->get();

        foreach ($paidPayments as $payment) {
            try {
                $refunded = $this->payments->refund($payment, (float) $payment->amount);
            } catch (\Throwable $e) {
                $refunded = false;

                Log::warning('Order cancelled but refund threw; manual review required', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if (! $refunded) {
                Log::warning('Order cancelled but refund failed; manual review required', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                ]);
            }
        }
    }

    public function expireOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->find($order->id);

            if (! $order || $order->status !== 'pending') {
                return;
            }

            $order->update(['status' => 'expired']);
            $this->releaseInventoryForOrder($order);
            $this->discountService->releaseCouponRedemptionsForOrder($order);
        });
    }

    public function handlePaymentFailure(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->find($order->id);

            if (! $order || $order->status !== 'pending') {
                return;
            }

            $this->updateStatus($order, 'cancelled');
            $this->releaseInventoryForOrder($order);
            $this->discountService->releaseCouponRedemptionsForOrder($order);
        });
    }

    public function createPaymentForOrder(Order $order, string $paymentMethod): Payment
    {
        return $order->payments()->create([
            'method' => $paymentMethod,
            'status' => 'pending',
            'amount' => $order->total,
            'gateway' => $paymentMethod,
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

            // The cart's own reservation transfers to the order (released then
            // re-reserved below), so validate against physical stock here.
            // reserveInventoryForOrder() enforces true availability atomically.
            if ($inventory->quantity < $item->quantity) {
                throw new \InvalidArgumentException(
                    "Insufficient stock for '{$product->name}'. Available: {$inventory->quantity}, requested: {$item->quantity}."
                );
            }
        }
    }

    /**
     * VAT on the discounted value of taxable lines plus shipping.
     * Exempt lines (zero-rated/exempt categories) are excluded pro-rata.
     * Returns 0 when tax is disabled for this deployment.
     */
    private function calculateOrderTax(Cart $cart, float $subtotal, float $discountTotal, float $shippingCost): float
    {
        $storeId = $cart->store_id;

        if (! $this->taxService->enabled($storeId) || $subtotal <= 0) {
            return 0.0;
        }

        $taxableBase = $shippingCost;

        foreach ($cart->items as $item) {
            if ($this->taxService->isExempt($item->product)) {
                continue;
            }

            $lineTotal = round($item->getUnitPrice() * $item->quantity, 2);
            $taxableBase += $lineTotal - ($discountTotal * ($lineTotal / $subtotal));
        }

        return $this->taxService->vatFor(round(max(0, $taxableBase), 2), $storeId);
    }

    /**
     * Queue a simplified (B2C) ZATCA document for a confirmed order.
     * No-op unless the deployment can submit. Never throws: invoicing
     * must not break order confirmation.
     */
    private function queueZatcaDocument(Order $order): void
    {
        try {
            if (! $this->zatcaDocuments->canSubmit($order->store_id)) {
                return;
            }

            if (ZatcaDocument::where('order_id', $order->id)->exists()) {
                return;
            }

            $document = $this->zatcaDocuments->buildForOrder($order, 'simplified');

            SubmitZatcaDocument::dispatch($document->id);
        } catch (\Throwable $e) {
            Log::warning('ZATCA document queueing failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function releaseCartReservations(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            try {
                $inventory = $this->getInventoryForItem($item);
                $this->inventoryService->release($inventory, $item->quantity);
            } catch (\InvalidArgumentException) {
                // Inventory row gone (test helpers create rows directly) — nothing to release.
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
            if ($order->coupon_id && $discountId === $order->coupon?->discount_id) {
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
            if ($order->coupon_id && $discountId === $order->coupon?->discount_id) {
                continue;
            }

            $discount = Discount::find($discountId);
            if ($discount) {
                $this->discountService->decrementUsage($discount);
            }
        }
    }
}
