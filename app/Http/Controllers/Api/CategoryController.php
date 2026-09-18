<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogCache;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = CatalogCache::rememberCategories(fn () => Category::active()
            ->withCount('children')
            ->with(['children' => function ($q) {
                $q->active()->withCount('children');
            }])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($category) => $this->formatCategory($category)));

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::active()
            ->where('slug', $slug)
            ->with(['parent', 'children' => function ($q) {
                $q->active()->withCount('children');
            }])
            ->firstOrFail();

        $categoryIds = $this->getDescendantCategoryIds($category->id);

        $products = Product::active()
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->with('brand')
            ->withCount('variants')
            ->withCount(['reviews as approved_reviews_count' => fn ($q) => $q->approved()])
            ->withAvg(['reviews as approved_reviews_avg' => fn ($q) => $q->approved()], 'rating')
            ->with(['images' => fn ($q) => $q->primary()->limit(1)])
            ->paginate(15)
            ->withQueryString();

        $products->getCollection()->transform(fn ($product) => [
            'id' => $product->id,
            'name' => $product->displayName(),
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
            'primary_image' => $product->images->first()?->getUrl('medium'),
            'variants_count' => $product->variants_count,
            'review_summary' => [
                'total' => (int) ($product->approved_reviews_count ?? 0),
                'average' => (float) ($product->approved_reviews_avg ?? 0),
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'name' => $category->displayName(),
                'slug' => $category->slug,
                'description' => $category->displayDescription(),
                'parent' => $category->parent ? [
                    'id' => $category->parent->id,
                    'name' => $category->parent->displayName(),
                    'slug' => $category->parent->slug,
                ] : null,
                'children' => $category->children->map(fn ($child) => [
                    'id' => $child->id,
                    'name' => $child->displayName(),
                    'slug' => $child->slug,
                    'children_count' => $child->children_count,
                ]),
                'products' => $products,
            ],
        ]);
    }

    private function formatCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'children_count' => $category->children_count,
            'children' => $category->children->map(fn ($child) => [
                'id' => $child->id,
                'name' => $child->name,
                'slug' => $child->slug,
                'children_count' => $child->children_count,
            ]),
        ];
    }

    private function getDescendantCategoryIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = Category::where('parent_id', $categoryId)->pluck('id')->toArray();

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getDescendantCategoryIds($childId));
        }

        return $ids;
    }
}
