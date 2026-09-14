<?php

namespace App\Services\Discounts;

use App\Models\Coupon;
use App\Models\Discount;
use Illuminate\Database\Eloquent\Collection;

interface DiscountCombiner
{
    /**
     * Combine applicable automatic discounts (and an optional validated
     * coupon) into a single result.
     *
     * @param  Collection<int, Discount>  $automaticDiscounts  Applicable, non-coupon-only discounts.
     * @param  array{discount: Discount, coupon: Coupon}|null  $coupon
     * @param  array<int, array{cart_item_id?: int|string, product_id: int, variant_id?: int|null, total: float}>  $items
     * @return array|null Same shape as DiscountService::bestDiscountForOrder().
     */
    public function combine(
        Collection $automaticDiscounts,
        ?array $coupon,
        float $subtotal,
        array $productIds,
        array $productTotals,
        array $variantIds,
        array $items,
    ): ?array;
}
