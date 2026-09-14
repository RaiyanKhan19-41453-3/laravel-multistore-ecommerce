<?php

namespace App\Services\Discounts;

use Illuminate\Database\Eloquent\Collection;

/**
 * Best-per-line mode: every line takes its highest-value discount.
 * Priority only breaks ties between equal amounts on the same line.
 */
class BestPerLineCombiner extends BaseDiscountCombiner
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

        $autoShares = [];
        $reducedTotals = $productTotals;

        foreach ($this->orderByPriority($automaticDiscounts) as $discount) {
            if ($discount->coupon_only) {
                continue;
            }

            $perItem = $this->discounts->getPerItemDiscountAmounts(
                $discount, $productIds, $reducedTotals, $variantIds, $items
            );

            foreach ($perItem as $key => $info) {
                if ($info['amount'] <= 0) {
                    continue;
                }

                $current = $autoShares[$key] ?? null;

                if ($current === null
                    || $info['amount'] > $current['amount']
                    || ($info['amount'] === $current['amount']
                        && ($discount->priority ?? 0) > ($current['discount']->priority ?? 0))) {
                    $autoShares[$key] = [
                        'discount' => $discount,
                        'amount' => $info['amount'],
                    ];
                }
            }
        }

        if ($coupon) {
            $autoShares = $this->mergeCouponShares(
                $coupon, $autoShares, $lines, $productIds, $reducedTotals, $variantIds, $items
            );
        }

        return $this->buildResult($this->aggregateShares($autoShares));
    }
}
