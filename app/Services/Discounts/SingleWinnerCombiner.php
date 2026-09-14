<?php

namespace App\Services\Discounts;

use Illuminate\Database\Eloquent\Collection;

/**
 * Legacy mode: the single highest-priority discount takes the whole order.
 * Totals are computed at order level, exactly as before.
 */
class SingleWinnerCombiner extends BaseDiscountCombiner
{
    public function combine(
        Collection $automaticDiscounts,
        ?array $coupon,
        float $subtotal,
        array $productIds,
        array $productTotals,
        array $variantIds,
        array $items,
    ): ?array {
        $bestAutomatic = null;
        $bestAutoPriority = -1;
        $bestAutoAmount = 0;

        foreach ($automaticDiscounts as $discount) {
            if ($discount->coupon_only) {
                continue;
            }

            $eligibleSubtotal = $this->discounts->getEligibleSubtotal(
                $discount, $productIds, $productTotals, $subtotal, $variantIds, $items
            );
            $amount = $discount->getEffectiveDiscount($eligibleSubtotal);
            $priority = $discount->priority ?? 0;

            if ($amount <= 0) {
                continue;
            }

            if ($priority > $bestAutoPriority || ($priority === $bestAutoPriority && $amount > $bestAutoAmount)) {
                $bestAutoPriority = $priority;
                $bestAutoAmount = $amount;
                $bestAutomatic = [
                    'discount' => $discount,
                    'amount' => $amount,
                ];
            }
        }

        $couponDiscount = null;

        if ($coupon) {
            $reducedSubtotal = max(0, $subtotal - ($bestAutomatic['amount'] ?? 0));
            $couponEligibleSubtotal = $this->discounts->getEligibleSubtotal(
                $coupon['discount'], $productIds, $productTotals, $reducedSubtotal, $variantIds, $items
            );
            $amount = $coupon['discount']->getEffectiveDiscount($couponEligibleSubtotal);

            if ($amount > 0) {
                $couponDiscount = [
                    'discount' => $coupon['discount'],
                    'coupon' => $coupon['coupon'],
                    'amount' => $amount,
                ];
            }
        }

        if ($couponDiscount && $bestAutomatic) {
            $autoStackable = (bool) $bestAutomatic['discount']->stackable;
            $couponStackable = (bool) $couponDiscount['discount']->stackable;

            if ($autoStackable && $couponStackable) {
                return [
                    'discounts' => [$bestAutomatic, $couponDiscount],
                    'total_amount' => round($bestAutomatic['amount'] + $couponDiscount['amount'], 2),
                    'stacked' => true,
                ];
            }

            if ($couponDiscount['amount'] >= $bestAutomatic['amount']) {
                return $couponDiscount;
            }

            return $bestAutomatic;
        }

        return $couponDiscount ?? $bestAutomatic;
    }
}
