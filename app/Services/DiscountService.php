<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DiscountService
{
    public function getActiveForProduct(Product $product): Collection
    {
        $now = now();
        $categoryIds = $product->categories()->pluck('categories.id');

        return Discount::where('is_active', true)
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

        if (! $coupon->discount->isActiveNow()) {
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

        return $discount->where('usage_count', '<', $discount->usage_limit)
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

        return $coupon->where('usage_count', '<', $coupon->usage_limit)
            ->increment('usage_count') > 0;
    }

    public function decrementCouponUsage(Coupon $coupon): bool
    {
        if ($coupon->usage_count <= 0) {
            return false;
        }

        return $coupon->decrement('usage_count') !== false;
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

    public function bestDiscountForOrder(float $subtotal, ?string $couponCode = null, array $productIds = [], array $productTotals = [], array $variantIds = [], array $items = []): ?array
    {
        $now = now();

        $coupon = null;
        $couponDiscountId = null;
        if ($couponCode) {
            $coupon = $this->applyCoupon($couponCode);
            if ($coupon) {
                $couponDiscountId = $coupon->discount_id;
            }
        }

        $automaticQuery = Discount::where('is_active', true)
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

        $bestAutomatic = null;
        $bestAutoPriority = -1;
        $bestAutoAmount = 0;

        foreach ($automaticDiscounts as $discount) {
            if ($discount->coupon_only) {
                continue;
            }

            $eligibleSubtotal = $this->getEligibleSubtotal($discount, $productIds, $productTotals, $subtotal, $variantIds, $items);
            $amount = $discount->getEffectiveDiscount($eligibleSubtotal);
            $priority = $discount->priority ?? 0;

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
            $amount = $coupon->discount->getEffectiveDiscount($reducedSubtotal);

            if ($amount > 0) {
                $couponDiscount = [
                    'discount' => $coupon->discount,
                    'coupon' => $coupon,
                    'amount' => $amount,
                ];
            }
        }

        if ($couponDiscount && $bestAutomatic) {
            return [
                'discounts' => [$bestAutomatic, $couponDiscount],
                'total_amount' => round($bestAutomatic['amount'] + $couponDiscount['amount'], 2),
                'stacked' => true,
            ];
        }

        return $couponDiscount ?? $bestAutomatic;
    }

    public function getPerItemDiscountAmounts(Discount $discount, array $productIds, array $productTotals, array $variantIds = [], array $items = []): array
    {
        $eligibleSubtotal = $this->getEligibleSubtotal($discount, $productIds, $productTotals, 0, $variantIds, $items);

        if ($eligibleSubtotal <= 0) {
            return [];
        }

        $level = $this->getDiscountLevel($discount);
        $target = $this->getDiscountTarget($discount);

        $totalAmount = $discount->getEffectiveDiscount($eligibleSubtotal);
        if ($totalAmount <= 0) {
            return [];
        }

        $hasVariantTargets = $discount->productVariants()->exists();
        $discountVariantIds = $hasVariantTargets
            ? $discount->productVariants()->pluck('product_variants.id')->toArray()
            : [];

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

            if (empty($eligibleProductIds)) {
                return [];
            }

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
        if ($discount->products()->exists()) {
            return 'product';
        }

        if ($discount->productVariants()->exists()) {
            return 'variant';
        }

        if ($discount->categories()->exists()) {
            return 'category';
        }

        if ($discount->brands()->exists()) {
            return 'brand';
        }

        return 'sitewide';
    }

    public function getDiscountTarget(Discount $discount): ?string
    {
        return match ($this->getDiscountLevel($discount)) {
            'category' => $discount->categories()->first()?->name,
            'brand' => $discount->brands()->first()?->name,
            'variant' => $discount->productVariants()->first()?->name,
            default => null,
        };
    }

    private function getEligibleProductIds(Discount $discount, array $productIds, array $variantIds = []): array
    {
        $eligibleProductIds = [];

        $discountProductIds = $discount->products()->pluck('products.id')->toArray();
        if (! empty($discountProductIds)) {
            $eligibleProductIds = array_merge($eligibleProductIds, array_intersect($productIds, $discountProductIds));
        }

        $discountCategoryIds = $discount->categories()->pluck('categories.id')->toArray();
        if (! empty($discountCategoryIds)) {
            $categoryProductIds = DB::table('category_product')
                ->whereIn('category_id', $discountCategoryIds)
                ->whereIn('product_id', $productIds)
                ->pluck('product_id')
                ->toArray();
            $eligibleProductIds = array_merge($eligibleProductIds, $categoryProductIds);
        }

        $discountBrandIds = $discount->brands()->pluck('brands.id')->toArray();
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

    private function getEligibleSubtotal(Discount $discount, array $productIds, array $productTotals, float $fallbackSubtotal, array $variantIds = [], array $items = []): float
    {
        if (empty($productTotals) && empty($items)) {
            return $fallbackSubtotal;
        }

        $hasVariantTargets = $discount->productVariants()->exists();

        if ($hasVariantTargets && ! empty($items)) {
            $discountVariantIds = $discount->productVariants()->pluck('product_variants.id')->toArray();
            $eligibleSubtotal = 0;
            foreach ($items as $item) {
                if (in_array($item['variant_id'] ?? null, $discountVariantIds)) {
                    $eligibleSubtotal += $item['total'];
                }
            }

            return $eligibleSubtotal > 0 ? $eligibleSubtotal : $fallbackSubtotal;
        }

        $eligibleProductIds = $this->getEligibleProductIds($discount, $productIds, $variantIds);

        if (! empty($eligibleProductIds)) {
            $eligibleSubtotal = 0;
            foreach ($eligibleProductIds as $pid) {
                $eligibleSubtotal += $productTotals[$pid] ?? 0;
            }

            if ($eligibleSubtotal > 0) {
                return $eligibleSubtotal;
            }
        }

        return $fallbackSubtotal;
    }

    private function whereNoTargets($query): void
    {
        $query->whereDoesntHave('products')
            ->whereDoesntHave('productVariants')
            ->whereDoesntHave('categories')
            ->whereDoesntHave('brands');
    }
}
