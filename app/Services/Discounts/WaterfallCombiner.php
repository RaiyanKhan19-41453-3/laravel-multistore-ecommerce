<?php

namespace App\Services\Discounts;

use Illuminate\Database\Eloquent\Collection;

/**
 * Waterfall mode: priority-ranked discounts claim the lines they cover.
 * Lines no discount covers fall through to the next discount, so targeted
 * sales never block each other on lines they don't compete on.
 */
class WaterfallCombiner extends BaseDiscountCombiner
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
        if (empty($items) && empty($productTotals)) {
            return (new SingleWinnerCombiner($this->discounts))->combine(
                $automaticDiscounts, $coupon, $subtotal, $productIds, $productTotals, $variantIds, $items
            );
        }

        $lines = $this->normalizeLines($items, $productIds, $productTotals);

        $claimed = [];
        $autoShares = [];
        $remainingItems = $items;
        $remainingTotals = $productTotals;
        $reducedTotals = $productTotals;

        foreach ($this->orderByPriority($automaticDiscounts) as $discount) {
            if ($discount->coupon_only) {
                continue;
            }

            $perItem = $this->discounts->getPerItemDiscountAmounts(
                $discount, $productIds, $remainingTotals, $variantIds, $remainingItems
            );

            foreach ($perItem as $key => $info) {
                if ($info['amount'] <= 0 || isset($claimed[$key])) {
                    continue;
                }

                $claimed[$key] = true;
                $autoShares[$key] = [
                    'discount' => $discount,
                    'amount' => $info['amount'],
                ];

                $pid = $this->productIdForKey($key, $lines);
                $reducedTotals[$pid] = max(0, ($reducedTotals[$pid] ?? 0) - $info['amount']);
            }

            $remainingItems = $this->dropClaimedItems($remainingItems, $claimed);
            $remainingTotals = $this->totalsForItems($remainingItems, $reducedTotals);
        }

        if ($coupon) {
            $autoShares = $this->mergeCouponShares(
                $coupon, $autoShares, $lines, $productIds, $reducedTotals, $variantIds, $items
            );
        }

        return $this->buildResult($this->aggregateShares($autoShares));
    }

    /**
     * Drop claimed lines so later discounts evaluate only what is left.
     * Totals are rebuilt from the surviving lines.
     */
    private function dropClaimedItems(array $items, array $claimed): array
    {
        if (empty($items)) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            fn ($item) => ! isset($claimed[$item['cart_item_id'] ?? $item['product_id']])
        ));
    }

    private function totalsForItems(array $items, array $fallbackTotals): array
    {
        if (empty($items)) {
            return $fallbackTotals;
        }

        $totals = [];

        foreach ($items as $item) {
            $pid = $item['product_id'];
            $totals[$pid] = ($totals[$pid] ?? 0) + $item['total'];
        }

        return $totals;
    }
}
