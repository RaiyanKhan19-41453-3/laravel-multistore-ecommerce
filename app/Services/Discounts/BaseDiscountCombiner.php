<?php

namespace App\Services\Discounts;

use App\Models\Coupon;
use App\Models\Discount;
use App\Services\DiscountService;
use Illuminate\Database\Eloquent\Collection;

abstract class BaseDiscountCombiner implements DiscountCombiner
{
    public function __construct(
        protected DiscountService $discounts,
    ) {}

    /**
     * Normalize cart lines to claim keys. Mirrors the keying used by
     * DiscountService::getPerItemDiscountAmounts so claims always line up.
     *
     * @return array<int|string, array{product_id: int, total: float}>
     */
    protected function normalizeLines(array $items, array $productIds, array $productTotals): array
    {
        if (! empty($items)) {
            $lines = [];

            foreach ($items as $item) {
                $key = $item['cart_item_id'] ?? $item['product_id'];
                $lines[$key] = [
                    'product_id' => $item['product_id'],
                    'total' => $item['total'],
                ];
            }

            return $lines;
        }

        $lines = [];

        foreach ($productIds as $pid) {
            $lines[$pid] = [
                'product_id' => $pid,
                'total' => $productTotals[$pid] ?? 0,
            ];
        }

        return $lines;
    }

    /**
     * Map a claim key back to its product id for reducing product totals.
     */
    protected function productIdForKey(int|string $key, array $lines): int|string
    {
        return $lines[$key]['product_id'] ?? $key;
    }

    /**
     * Merge a validated coupon into per-line automatic shares. A line sums
     * both shares only when both sides are stackable; otherwise the line
     * takes the better deal (coupon wins ties).
     *
     * @param  array{discount: Discount, coupon: Coupon}  $coupon
     * @param  array<int|string, array{discount: Discount, amount: float}>  $autoShares
     * @param  array<int|string, array{product_id: int, total: float}>  $lines
     * @param  array<int, array{cart_item_id?: int|string, product_id: int, variant_id?: int|null, total: float}>  $itemsForMath
     * @return array<int|string, array{discount: Discount, amount: float, coupon?: Coupon}>
     */
    protected function mergeCouponShares(
        array $coupon,
        array $autoShares,
        array $lines,
        array $productIds,
        array $reducedTotals,
        array $variantIds,
        array $itemsForMath,
    ): array {
        $couponDiscount = $coupon['discount'];

        $couponMap = $this->discounts->getPerItemDiscountAmounts(
            $couponDiscount,
            $productIds,
            $reducedTotals,
            $variantIds,
            $itemsForMath,
        );

        if (empty($couponMap)) {
            return $autoShares;
        }

        $merged = $autoShares;

        foreach ($lines as $key => $line) {
            $couponShare = $couponMap[$key]['amount'] ?? 0;

            if ($couponShare <= 0) {
                continue;
            }

            $auto = $merged[$key] ?? null;

            if ($auto === null) {
                $merged[$key] = [
                    'discount' => $couponDiscount,
                    'amount' => $couponShare,
                    'coupon' => $coupon['coupon'],
                ];
            } elseif ($auto['discount']->stackable && $couponDiscount->stackable) {
                $merged[$key] = [
                    'discount' => $couponDiscount,
                    'amount' => $auto['amount'] + $couponShare,
                    'auto_amount' => $auto['amount'],
                    'coupon_amount' => $couponShare,
                    'coupon' => $coupon['coupon'],
                    'stacked_with' => $auto['discount'],
                ];
            } elseif ($couponShare > $auto['amount']) {
                $merged[$key] = [
                    'discount' => $couponDiscount,
                    'amount' => $couponShare,
                    'coupon' => $coupon['coupon'],
                ];
            }
        }

        return $merged;
    }

    /**
     * Aggregate per-line shares into per-discount contributors.
     * Stacked lines credit the automatic part to its discount and the
     * coupon part to the coupon's discount.
     */
    protected function aggregateShares(array $shares): array
    {
        $contributors = [];

        foreach ($shares as $share) {
            if (isset($share['stacked_with'])) {
                $contributors = $this->addContribution(
                    $contributors,
                    $share['stacked_with'],
                    $share['auto_amount'] ?? 0,
                    null
                );
                $contributors = $this->addContribution(
                    $contributors,
                    $share['discount'],
                    $share['coupon_amount'] ?? $share['amount'],
                    $share['coupon'] ?? null,
                );

                continue;
            }

            $contributors = $this->addContribution(
                $contributors,
                $share['discount'],
                $share['amount'],
                $share['coupon'] ?? null,
            );
        }

        return array_values($contributors);
    }

    private function addContribution(array $contributors, Discount $discount, float $amount, mixed $coupon): array
    {
        if ($amount <= 0) {
            return $contributors;
        }

        foreach ($contributors as &$entry) {
            if ($entry['discount']->id === $discount->id) {
                $entry['amount'] = round($entry['amount'] + $amount, 2);

                if ($coupon) {
                    $entry['coupon'] = $coupon;
                }

                unset($entry);

                return $contributors;
            }
        }
        unset($entry);

        $entry = ['discount' => $discount, 'amount' => round($amount, 2)];

        if ($coupon) {
            $entry['coupon'] = $coupon;
        }

        $contributors[] = $entry;

        return $contributors;
    }

    /**
     * Shape the final result to match the bestDiscountForOrder contract:
     * single shape for one contributor, stacked shape otherwise.
     */
    protected function buildResult(array $contributors): ?array
    {
        $contributors = array_values(array_filter(
            $contributors,
            fn ($entry) => $entry['amount'] > 0
        ));

        if (empty($contributors)) {
            return null;
        }

        if (count($contributors) === 1) {
            return $contributors[0];
        }

        $total = 0;

        foreach ($contributors as $entry) {
            $total += $entry['amount'];
        }

        return [
            'discounts' => $contributors,
            'total_amount' => round($total, 2),
            'stacked' => true,
        ];
    }

    /**
     * Deterministic discount ordering: priority first, then id.
     * Uses two stable sorts so ties always resolve the same way.
     *
     * @return Collection<int, Discount>
     */
    protected function orderByPriority(Collection $discounts): Collection
    {
        return $discounts
            ->sortBy(fn (Discount $d) => $d->id)
            ->sortByDesc(fn (Discount $d) => $d->priority ?? 0)
            ->values();
    }
}
