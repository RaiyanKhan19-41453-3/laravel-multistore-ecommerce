<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\CatalogCache;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        $brands = CatalogCache::rememberBrands(fn () => Brand::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'logo' => $brand->logo ? '/storage/'.$brand->logo : null,
            ]));

        return response()->json([
            'success' => true,
            'data' => $brands,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $brand = Brand::active()
            ->where('slug', $slug)
            ->firstOrFail();

        $products = $brand->products()->active()
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
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->displayName(),
                'slug' => $brand->slug,
            ],
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
                'id' => $brand->id,
                'name' => $brand->displayName(),
                'slug' => $brand->slug,
                'description' => $brand->displayDescription(),
                'logo' => $brand->logo ? '/storage/'.$brand->logo : null,
                'products' => $products,
            ],
        ]);
    }
}
