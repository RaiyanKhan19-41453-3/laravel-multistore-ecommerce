<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Discounts\DiscountCombinerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DiscountService
{
    public function getActiveForProduct(Product $product): Collection
    {
        $now = now();
        $categoryIds = $product->categories()->pluck('categories.id');

        return Discount::where('is_active', true)
            ->when($product->store_id, fn ($q) => $q->where('discounts.store_id', $product->store_id))
            ->where('coupon_only', false)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->where(function ($query) use ($product, $categoryIds) {
                $query->whereHas('products', fn ($q) => $q->where('products.id', $product->id))
                    ->orWhereHas('productVariants', fn ($q) => $q->where('product_variants.product_id', $product->id))
                    ->orWhereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
                    ->orWhereHas('brands', fn ($q) => $q->where('brands.id', $product->brand_id))
                    ->orWhere(fn ($q) => $this->whereNoTargets($q));
            })
            ->orderByDesc('priority')
            ->get();
    }

    public function getActiveForCategory(Category $category): Collection
    {
        $now = now();

        return Discount::where('is_active', true)
            ->when($category->store_id, fn ($q) => $q->where('discounts.store_id', $category->store_id))
            ->where('coupon_only', false)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->where(function ($query) use ($category) {
                $query->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
                    ->orWhere(fn ($q) => $this->whereNoTargets($q));
            })
            ->orderByDesc('priority')
            ->get();
    }

    public function getActiveForBrand(Brand $brand): Collection
    {
        $now = now();

        return Discount::where('is_active', true)
            ->when($brand->store_id, fn ($q) => $q->where('discounts.store_id', $brand->store_id))
            ->where('coupon_only', false)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->where(function ($query) use ($brand) {
                $query->whereHas('brands', fn ($q) => $q->where('brands.id', $brand->id))
                    ->orWhere(fn ($q) => $this->whereNoTargets($q));
            })
            ->orderByDesc('priority')
            ->get();
    }

    public function calculateDiscount(Discount $discount, float $subtotal): float
    {
        return $discount->getEffectiveDiscount($subtotal);
    }

    public function applyCoupon(string $code): ?Coupon
    {
        $coupon = Coupon::with('discount')->where('code', strtoupper($code))->first();

        if (! $coupon) {
            return null;
        }

        if (! $coupon->isActiveNow()) {
            return null;
        }

        if (! $coupon->discount || ! $coupon->discount->isActiveNow()) {
            return null;
        }

        return $coupon;
    }

    public function incrementUsage(Discount $discount): bool
    {
        if ($discount->usage_limit === null) {
            $discount->increment('usage_count');

            return true;
        }

        return Discount::whereKey($discount->id)
            ->where('usage_count', '<', $discount->usage_limit)
            ->increment('usage_count') > 0;
    }

    public function decrementUsage(Discount $discount): bool
    {
        if ($discount->usage_count <= 0) {
            return false;
        }

        return $discount->decrement('usage_count') !== false;
    }

    public function incrementCouponUsage(Coupon $coupon): bool
    {
        if ($coupon->usage_limit === null) {
            $coupon->increment('usage_count');

            return true;
        }

        return Coupon::whereKey($coupon->id)
            ->where('usage_count', '<', $coupon->usage_limit)
            ->increment('usage_count') > 0;
    }

    public function decrementCouponUsage(Coupon $coupon): bool
    {
        if ($coupon->usage_count <= 0) {
            return false;
        }

        return $coupon->decrement('usage_count') !== false;
    }

    public function redemptionIdentifier(?User $user, ?string $guestEmail): ?string
    {
        if ($user) {
            return 'user:'.$user->id;
        }

        if ($guestEmail) {
            return 'guest:'.strtolower(trim($guestEmail));
        }

        return null;
    }

    public function assertCouponRedeemable(Coupon $coupon, ?string $identifier): void
    {
        if ($identifier === null || $coupon->per_user_limit === null) {
            return;
        }

        // Re-read under lock so concurrent checkouts serialize on the coupon
        // row. Must run inside the order transaction to hold the lock.
        $coupon = Coupon::lockForUpdate()->find($coupon->id) ?? $coupon;

        if ($this->countCouponRedemptions($coupon, $identifier) >= $coupon->per_user_limit) {
            throw new \InvalidArgumentException('This coupon has already been used the maximum number of times.');
        }
    }

    public function countCouponRedemptions(Coupon $coupon, string $identifier): int
    {
        return CouponRedemption::where('coupon_id', $coupon->id)
            ->where('identifier', $identifier)
            ->count();
    }

    public function reserveCouponRedemption(Coupon $coupon, string $identifier, int $orderId): void
    {
        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'identifier' => $identifier,
            'order_id' => $orderId,
        ]);
    }

    public function releaseCouponRedemptionsForOrder(Order $order): void
    {
        if (! $order->coupon_id) {
            return;
        }

        $identifier = $this->redemptionIdentifier($order->user, $order->guest_email);

        CouponRedemption::where('coupon_id', $order->coupon_id)
            ->when($identifier, fn ($q) => $q->where('identifier', $identifier))
            ->where('order_id', $order->id)
            ->delete();
    }

    public function bestDiscountForProduct(Product $product, float $subtotal): ?array
    {
        $discounts = $this->getActiveForProduct($product);

        $best = null;
        $bestPriority = -1;
        $bestAmount = 0;

        foreach ($discounts as $discount) {
            $amount = $discount->getEffectiveDiscount($subtotal);
            $priority = $discount->priority ?? 0;

            if ($amount <= 0) {
                continue;
            }

            if ($priority > $bestPriority || ($priority === $bestPriority && $amount > $bestAmount)) {
                $bestPriority = $priority;
                $bestAmount = $amount;
                $best = [
                    'discount' => $discount,
                    'amount' => $amount,
                ];
            }
        }

        return $best;
    }

    public function bestDiscountForOrder(float $subtotal, ?string $couponCode = null, array $productIds = [], array $productTotals = [], array $variantIds = [], array $items = [], ?string $couponIdentifier = null, ?int $storeId = null): ?array
    {
        $now = now();

        $coupon = null;
        $couponDiscountId = null;
        if ($couponCode) {
            $coupon = $this->applyCoupon($couponCode);

            if ($coupon && $couponIdentifier !== null && $coupon->per_user_limit !== null
                && $this->countCouponRedemptions($coupon, $couponIdentifier) >= $coupon->per_user_limit) {
                $coupon = null;
            }

            if ($coupon) {
                $couponDiscountId = $coupon->discount_id;
            }
        }

        $automaticQuery = Discount::where('is_active', true)
            ->when($storeId !== null, fn ($q) => $q->where('discounts.store_id', $storeId))
            ->when($couponDiscountId, fn ($q) => $q->where('id', '!=', $couponDiscountId))
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });

        if (! empty($productIds)) {
            $automaticQuery->where(function ($query) use ($productIds, $variantIds) {
                $query->whereHas('products', fn ($q) => $q->whereIn('products.id', $productIds))
                    ->orWhereHas('productVariants', fn ($q) => $q->whereIn('product_variants.id', $variantIds))
                    ->orWhereHas('categories', function ($q) use ($productIds) {
                        $q->whereIn('categories.id', function ($cq) use ($productIds) {
                            $cq->select('category_id')
                                ->from('category_product')
                                ->whereIn('product_id', $productIds);
                        });
                    })
                    ->orWhereHas('brands', function ($q) use ($productIds) {
                        $q->whereIn('brands.id', function ($bq) use ($productIds) {
                            $bq->select('brand_id')
                                ->from('products')
                                ->whereIn('id', $productIds);
                        });
                    })
                    ->orWhere(fn ($q) => $this->whereNoTargets($q));
            });
        }

        $automaticDiscounts = $automaticQuery->orderByDesc('priority')->get();

        $combiner = DiscountCombinerFactory::make(
            config('discounts.combination_mode', DiscountCombinerFactory::MODE_WATERFALL),
            $this,
        );

        return $combiner->combine(
            $automaticDiscounts,
            $coupon ? ['discount' => $coupon->discount, 'coupon' => $coupon] : null,
            $subtotal,
            $productIds,
            $productTotals,
            $variantIds,
            $items,
        );
    }

    public function getPerItemDiscountAmounts(Discount $discount, array $productIds, array $productTotals, array $variantIds = [], array $items = []): array
    {
        $eligibleSubtotal = $this->getEligibleSubtotal($discount, $productIds, $productTotals, 0, $variantIds, $items);

        if ($eligibleSubtotal <= 0) {
            return [];
        }

        $targeting = $this->getTargeting($discount);
        $level = $targeting['level'];
        $target = $targeting['target'];

        $totalAmount = $discount->getEffectiveDiscount($eligibleSubtotal);
        if ($totalAmount <= 0) {
            return [];
        }

        $hasVariantTargets = ! empty($targeting['variant_ids']);
        $discountVariantIds = $targeting['variant_ids'];

        $perItem = [];

        if ($hasVariantTargets && ! empty($items)) {
            $eligibleItemTotals = [];
            foreach ($items as $item) {
                if (in_array($item['variant_id'] ?? null, $discountVariantIds)) {
                    $eligibleItemTotals[] = $item['total'];
                }
            }

            $eligibleItemTotal = array_sum($eligibleItemTotals);
            if ($eligibleItemTotal <= 0) {
                return [];
            }

            foreach ($items as $item) {
                if (! in_array($item['variant_id'] ?? null, $discountVariantIds)) {
                    continue;
                }

                $itemTotal = $item['total'];
                if ($itemTotal <= 0) {
                    continue;
                }

                if ($discount->type === 'percentage') {
                    $amount = $itemTotal * ((float) $discount->value / 100);
                } else {
                    $amount = ($itemTotal / $eligibleItemTotal) * (float) $discount->value;
                }

                $key = $item['cart_item_id'] ?? $item['product_id'];
                $perItem[$key] = [
                    'id' => $discount->id,
                    'name' => $discount->name,
                    'type' => $discount->type,
                    'level' => $level,
                    'target' => $target,
                    'amount' => round($amount, 2),
                ];
            }
        } else {
            $eligibleProductIds = $this->getEligibleProductIds($discount, $productIds, $variantIds);

            if (empty($eligibleProductIds) && $this->getTargeting($discount)['level'] === 'sitewide') {
                $eligibleProductIds = ! empty($items)
                    ? array_values(array_unique(array_filter(array_column($items, 'product_id'))))
                    : $productIds;
            }

            if (empty($eligibleProductIds)) {
                return [];
            }

            if (! empty($items)) {
                foreach ($items as $item) {
                    if (! in_array($item['product_id'] ?? null, $eligibleProductIds)) {
                        continue;
                    }

                    $itemTotal = $item['total'];
                    if ($itemTotal <= 0) {
                        continue;
                    }

                    if ($discount->type === 'percentage') {
                        $amount = $itemTotal * ((float) $discount->value / 100);
                    } else {
                        $amount = ($itemTotal / $eligibleSubtotal) * (float) $discount->value;
                    }

                    $key = $item['cart_item_id'] ?? $item['product_id'];
                    $perItem[$key] = [
                        'id' => $discount->id,
                        'name' => $discount->name,
                        'type' => $discount->type,
                        'level' => $level,
                        'target' => $target,
                        'amount' => round($amount, 2),
                    ];
                }
            } else {
                foreach ($eligibleProductIds as $pid) {
                    $productTotal = $productTotals[$pid] ?? 0;
                    if ($productTotal <= 0) {
                        continue;
                    }

                    if ($discount->type === 'percentage') {
                        $amount = $productTotal * ((float) $discount->value / 100);
                    } else {
                        $amount = ($productTotal / $eligibleSubtotal) * (float) $discount->value;
                    }

                    $perItem[$pid] = [
                        'id' => $discount->id,
                        'name' => $discount->name,
                        'type' => $discount->type,
                        'level' => $level,
                        'target' => $target,
                        'amount' => round($amount, 2),
                    ];
                }
            }
        }

        $rawTotal = array_sum(array_column($perItem, 'amount'));
        if ($rawTotal > $totalAmount && $rawTotal > 0) {
            $scale = $totalAmount / $rawTotal;
            foreach ($perItem as &$entry) {
                $entry['amount'] = round($entry['amount'] * $scale, 2);
            }
            unset($entry);
        }

        return $perItem;
    }

    public function getDiscountLevel(Discount $discount): string
    {
        return $this->getTargeting($discount)['level'];
    }

    public function getDiscountTarget(Discount $discount): ?string
    {
        return $this->getTargeting($discount)['target'];
    }

    /**
     * Load a discount's targeting relations once and derive its level,
     * display target, and variant ids from the loaded collections.
     * Repeated calls reuse the loaded relations — no extra queries.
     *
     * @return array{level: string, target: ?string, variant_ids: int[]}
     */
    public function getTargeting(Discount $discount): array
    {
        $discount->loadMissing(['products', 'productVariants', 'categories', 'brands']);

        if ($discount->products->isNotEmpty()) {
            return ['level' => 'product', 'target' => null, 'variant_ids' => []];
        }

        if ($discount->productVariants->isNotEmpty()) {
            return [
                'level' => 'variant',
                'target' => $discount->productVariants->first()->name,
                'variant_ids' => $discount->productVariants->pluck('id')->all(),
            ];
        }

        if ($discount->categories->isNotEmpty()) {
            return [
                'level' => 'category',
                'target' => $discount->categories->first()->name,
                'variant_ids' => [],
            ];
        }

        if ($discount->brands->isNotEmpty()) {
            return [
                'level' => 'brand',
                'target' => $discount->brands->first()->name,
                'variant_ids' => [],
            ];
        }

        return ['level' => 'sitewide', 'target' => null, 'variant_ids' => []];
    }

    private function getEligibleProductIds(Discount $discount, array $productIds, array $variantIds = []): array
    {
        $discount->loadMissing(['products', 'categories', 'brands']);

        $eligibleProductIds = [];

        $discountProductIds = $discount->products->pluck('id')->toArray();
        if (! empty($discountProductIds)) {
            $eligibleProductIds = array_merge($eligibleProductIds, array_intersect($productIds, $discountProductIds));
        }

        $discountCategoryIds = $discount->categories->pluck('id')->toArray();
        if (! empty($discountCategoryIds)) {
            $categoryProductIds = DB::table('category_product')
                ->whereIn('category_id', $discountCategoryIds)
                ->whereIn('product_id', $productIds)
                ->pluck('product_id')
                ->toArray();
            $eligibleProductIds = array_merge($eligibleProductIds, $categoryProductIds);
        }

        $discountBrandIds = $discount->brands->pluck('id')->toArray();
        if (! empty($discountBrandIds)) {
            $brandProductIds = DB::table('products')
                ->whereIn('brand_id', $discountBrandIds)
                ->whereIn('id', $productIds)
                ->pluck('id')
                ->toArray();
            $eligibleProductIds = array_merge($eligibleProductIds, $brandProductIds);
        }

        return array_unique($eligibleProductIds);
    }

    public function getEligibleSubtotal(Discount $discount, array $productIds, array $productTotals, float $fallbackSubtotal, array $variantIds = [], array $items = []): float
    {
        if (empty($productTotals) && empty($items)) {
            return $fallbackSubtotal;
        }

        $discount->loadMissing('productVariants');
        $hasVariantTargets = $discount->productVariants->isNotEmpty();

        if ($hasVariantTargets && ! empty($items)) {
            $discountVariantIds = $discount->productVariants->pluck('id')->toArray();
            $eligibleSubtotal = 0;
            foreach ($items as $item) {
                if (in_array($item['variant_id'] ?? null, $discountVariantIds)) {
                    $eligibleSubtotal += $item['total'];
                }
            }

            return (float) $eligibleSubtotal;
        }

        $eligibleProductIds = $this->getEligibleProductIds($discount, $productIds, $variantIds);

        if (empty($eligibleProductIds) && $this->getTargeting($discount)['level'] === 'sitewide') {
            $eligibleProductIds = ! empty($items)
                ? array_values(array_unique(array_filter(array_column($items, 'product_id'))))
                : $productIds;
        }

        if (! empty($eligibleProductIds)) {
            $eligibleSubtotal = 0;
            foreach ($eligibleProductIds as $pid) {
                $eligibleSubtotal += $productTotals[$pid] ?? 0;
            }

            if ($eligibleSubtotal <= 0 && ! empty($items)) {
                foreach ($items as $item) {
                    if (in_array($item['product_id'] ?? null, $eligibleProductIds)) {
                        $eligibleSubtotal += $item['total'];
                    }
                }
            }

            if ($eligibleSubtotal > 0) {
                return $eligibleSubtotal;
            }
        }

        return $this->getTargeting($discount)['level'] === 'sitewide' ? $fallbackSubtotal : 0.0;
    }

    private function whereNoTargets($query): void
    {
        $query->whereDoesntHave('products')
            ->whereDoesntHave('productVariants')
            ->whereDoesntHave('categories')
            ->whereDoesntHave('brands');
    }
}
