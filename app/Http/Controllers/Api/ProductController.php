<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Review;
use App\Services\CatalogCache;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected CatalogService $catalog = new CatalogService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->catalog->paginate($request->query());

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function featured(): JsonResponse
    {
        $products = CatalogCache::rememberFeatured(fn () => $this->catalog->featured());

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

        // Advertise only discounts checkout could actually grant (same
        // active/date bar the variant block below uses).
        $bestDiscount = $product->discounts
            ->filter(fn ($discount) => $discount->isActiveNow())
            ->sortByDesc('priority')
            ->first();

        $reviewSummary = [
            'total' => Review::approved()->where('product_id', $product->id)->count(),
            'average' => (float) Review::approved()->where('product_id', $product->id)->avg('rating'),
        ];

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
                'name' => $product->displayName(),
                'name_ar' => $product->name_ar,
                'slug' => $product->slug,
                'description' => $product->displayDescription(),
                'description_ar' => $product->description_ar,
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
                    'name' => $product->brand->displayName(),
                    'slug' => $product->brand->slug,
                ] : null,
                'categories' => $product->categories->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->displayName(),
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
                'review_summary' => $reviewSummary,
            ],
        ]);
    }
}
