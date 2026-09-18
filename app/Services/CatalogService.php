<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CatalogService
{
    /**
     * Paginate the storefront product listing. The returned items are plain
     * formatted arrays, so the result serializes identically for the JSON
     * API and for Inertia page props.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Product::active()
            ->with('brand')
            ->withCount('variants')
            ->withCount(['reviews as approved_reviews_count' => fn ($q) => $q->approved()])
            ->withAvg(['reviews as approved_reviews_avg' => fn ($q) => $q->approved()], 'rating');

        $query->with(['images' => fn ($q) => $q->primary()->limit(1)]);

        if ($search = $filters['search'] ?? null) {
            $search = mb_substr((string) $search, 0, 100);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($brandId = $filters['brand_id'] ?? null) {
            $query->where('brand_id', $brandId);
        }

        if ($categoryId = $filters['category_id'] ?? null) {
            $categoryIds = $this->getDescendantCategoryIds((int) $categoryId);
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds));
        }

        if (array_key_exists('is_featured', $filters)) {
            $query->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('on_sale', $filters) && filter_var($filters['on_sale'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price');
        }

        if ($minPrice = $filters['min_price'] ?? null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice = $filters['max_price'] ?? null) {
            $query->where('price', '<=', $maxPrice);
        }

        if ($attributeValues = $filters['attribute_values'] ?? null) {
            $attributeValueIds = is_array($attributeValues) ? $attributeValues : explode(',', $attributeValues);
            $query->whereHas('variants', function ($q) use ($attributeValueIds) {
                $q->whereHas('values', function ($vq) use ($attributeValueIds) {
                    $vq->whereIn('attribute_values.id', $attributeValueIds);
                });
            });
        }

        if ($discountId = $filters['discount_id'] ?? null) {
            $discount = Discount::with('categories', 'brands')->find($discountId);
            if ($discount) {
                $query->where(function ($q) use ($discount) {
                    $q->whereHas('discounts', fn ($dq) => $dq->where('discounts.id', $discount->id));

                    if ($discount->categories->isNotEmpty()) {
                        $categoryIds = $discount->categories->pluck('id')->flatMap(fn ($id) => $this->getDescendantCategoryIds($id))->unique();
                        $q->orWhereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $categoryIds));
                    }

                    if ($discount->brands->isNotEmpty()) {
                        $brandIds = $discount->brands->pluck('id');
                        $q->orWhereIn('brand_id', $brandIds);
                    }
                });
            }
        }

        $sort = $filters['sort'] ?? 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowed = ['name', 'price', 'created_at', 'rating'];

        if ($sort === 'rating') {
            // Order by the already-selected approved average. A join +
            // groupBy here breaks MySQL strict mode and turns the join
            // into an inner one, silently dropping unreviewed products.
            $query->orderBy('approved_reviews_avg', $direction);
        } elseif (in_array($sort, $allowed)) {
            $query->orderBy($sort, $direction);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        $products = $query->paginate($perPage)->withQueryString();

        $products->getCollection()->transform(fn ($product) => $this->formatProduct($product));

        return $products;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function featured(int $limit = 12): array
    {
        return Product::active()
            ->featured()
            ->with(['brand', 'images' => fn ($q) => $q->primary()->limit(1)])
            ->withCount('variants')
            ->withCount(['reviews as approved_reviews_count' => fn ($q) => $q->approved()])
            ->withAvg(['reviews as approved_reviews_avg' => fn ($q) => $q->approved()], 'rating')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get()
            ->map(fn ($product) => $this->formatProduct($product))
            ->all();
    }

    /**
     * @return Collection<int, Brand>
     */
    public function brandOptions(): Collection
    {
        return Brand::active()->orderBy('name')->get(['id', 'name', 'slug']);
    }

    /**
     * @return Collection<int, Category>
     */
    public function categoryOptions(): Collection
    {
        return Category::active()->orderBy('name')->get(['id', 'name', 'slug']);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatProduct(Product $product): array
    {
        $image = $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->displayName(),
            'name_ar' => $product->name_ar,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'sku' => $product->sku,
            'type' => $product->type,
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'is_featured' => $product->is_featured,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->displayName(),
                'slug' => $product->brand->slug,
            ] : null,
            'primary_image' => $image?->getUrl('medium'),
            'variants_count' => $product->variants_count,
            'review_summary' => [
                'total' => (int) ($product->approved_reviews_count ?? 0),
                'average' => (float) ($product->approved_reviews_avg ?? 0),
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function getDescendantCategoryIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = Category::where('parent_id', $categoryId)->pluck('id')->toArray();

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getDescendantCategoryIds($childId));
        }

        return $ids;
    }
}
