<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::active()
            ->with('brand')
            ->withCount('variants');

        $query->with(['images' => fn ($q) => $q->primary()->limit(1)]);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($brandId = $request->query('brand_id')) {
            $query->where('brand_id', $brandId);
        }

        if ($categoryId = $request->query('category_id')) {
            $categoryIds = $this->getDescendantCategoryIds($categoryId);
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds));
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        if ($discountId = $request->query('discount_id')) {
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

        $sort = $request->query('sort', 'name');
        $direction = $request->query('direction', 'asc');
        $allowed = ['name', 'price', 'created_at'];

        if (in_array($sort, $allowed)) {
            $query->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc');
        }

        $products = $query->paginate($request->integer('per_page', 15))->withQueryString();

        $products->getCollection()->transform(fn ($product) => $this->formatProduct($product));

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function featured(): JsonResponse
    {
        $products = Product::active()
            ->featured()
            ->with(['brand', 'images' => fn ($q) => $q->primary()->limit(1)])
            ->withCount('variants')
            ->orderBy('sort_order')
            ->limit($limit = 12)
            ->get()
            ->map(fn ($product) => $this->formatProduct($product));

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::active()
            ->where('slug', $slug)
            ->with([
                'brand',
                'categories',
                'images',
                'variants.values.attribute',
                'variants.images',
                'variants.inventory',
                'inventory',
                'discounts',
            ])
            ->firstOrFail();

        $image = $product->images()->primary()->first();

        $inventory = $product->isSimple()
            ? [
                'quantity' => $product->inventory?->quantity ?? 0,
                'available' => $product->inventory?->getAvailableQuantity() ?? 0,
                'in_stock' => $product->inventory?->getIsInStock() ?? false,
            ]
            : null;

        $variants = $product->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'price' => (float) $variant->price,
            'compare_at_price' => $variant->compare_at_price ? (float) $variant->compare_at_price : null,
            'is_active' => $variant->is_active,
            'values' => $variant->values->map(fn ($v) => [
                'id' => $v->id,
                'value' => $v->value,
                'attribute' => [
                    'id' => $v->attribute->id,
                    'name' => $v->attribute->name,
                ],
            ]),
            'image' => $variant->images->first()?->getUrl('medium'),
            'inventory' => [
                'quantity' => $variant->inventory?->quantity ?? 0,
                'available' => $variant->inventory?->getAvailableQuantity() ?? 0,
                'in_stock' => $variant->inventory?->getIsInStock() ?? false,
            ],
        ]);

        $bestDiscount = null;
        if ($product->discounts->isNotEmpty()) {
            $bestDiscountId = $product->discounts->sortByDesc('priority')->first()->id;
            $bestDiscount = $product->discounts->firstWhere('id', $bestDiscountId);
        }

        $variantDiscounts = [];
        if ($product->isVariable()) {
            $variantIds = $product->variants->pluck('id')->toArray();
            $now = now();
            $variantDiscountModels = Discount::where('is_active', true)
                ->with(['products', 'productVariants'])
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                })
                ->where(function ($q) use ($product, $variantIds) {
                    $q->whereHas('products', fn ($dq) => $dq->where('products.id', $product->id))
                        ->orWhereHas('productVariants', fn ($dq) => $dq->whereIn('product_variants.id', $variantIds));
                })
                ->get();

            foreach ($product->variants as $variant) {
                $applicable = $variantDiscountModels->filter(function ($d) use ($variant) {
                    if ($d->products->contains('id', $variant->product_id)) {
                        return true;
                    }

                    return $d->productVariants->contains('id', $variant->id);
                });

                if ($applicable->isNotEmpty()) {
                    $best = $applicable->sortByDesc('priority')->first();
                    $variantDiscounts[$variant->id] = [
                        'id' => $best->id,
                        'name' => $best->name,
                        'type' => $best->type,
                        'value' => (float) $best->value,
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'type' => $product->type,
                'price' => (float) $product->price,
                'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
                'is_active' => $product->is_active,
                'is_featured' => $product->is_featured,
                'created_at' => $product->created_at,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                    'slug' => $product->brand->slug,
                ] : null,
                'categories' => $product->categories->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ]),
                'images' => $product->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => $img->getUrl(),
                    'urls' => $img->getUrls(),
                    'alt_text' => $img->alt_text,
                    'is_primary' => $img->is_primary,
                    'sort_order' => $img->sort_order,
                ]),
                'primary_image' => $image?->getUrl('medium'),
                'variants' => $variants,
                'inventory' => $inventory,
                'discount' => $bestDiscount ? [
                    'id' => $bestDiscount->id,
                    'name' => $bestDiscount->name,
                    'type' => $bestDiscount->type,
                    'value' => (float) $bestDiscount->value,
                ] : null,
                'variant_discounts' => $variantDiscounts,
            ],
        ]);
    }

    private function formatProduct(Product $product): array
    {
        $image = $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'sku' => $product->sku,
            'type' => $product->type,
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'is_featured' => $product->is_featured,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'primary_image' => $image?->getUrl('medium'),
            'variants_count' => $product->variants_count,
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
