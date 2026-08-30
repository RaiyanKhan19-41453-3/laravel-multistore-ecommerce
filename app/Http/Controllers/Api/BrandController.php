<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        $brands = Brand::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'logo' => $brand->logo ? '/storage/'.$brand->logo : null,
            ]);

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

        $products = $brand->products()
            ->active()
            ->withCount('variants')
            ->with(['images' => fn ($q) => $q->primary()->limit(1)])
            ->paginate(15)
            ->withQueryString();

        $products->getCollection()->transform(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'sku' => $product->sku,
            'type' => $product->type,
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'is_featured' => $product->is_featured,
            'primary_image' => $product->images->first()?->getUrl('medium'),
            'variants_count' => $product->variants_count,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'logo' => $brand->logo ? '/storage/'.$brand->logo : null,
                'products' => $products,
            ],
        ]);
    }
}
