<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function index(Request $request): Response
    {
        $query = Product::with('brand', 'inventory', 'discounts', 'categories')->withCount('variants', 'images');

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
            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        if ($discountId = $request->query('discount_id')) {
            $discount = Discount::with('categories', 'brands')->find($discountId);
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

        $sort = $request->query('sort', 'name');
        $direction = $request->query('direction', 'asc');
        $allowed = ['name', 'sku', 'price', 'quantity', 'created_at'];

        if (in_array($sort, $allowed)) {
            $query->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc');
        }

        $products = $query->paginate(15)->withQueryString();

        $products->getCollection()->transform(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand_id' => $product->brand_id,
            'type' => $product->type,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'price' => $product->price,
            'compare_at_price' => $product->compare_at_price,
            'cost_price' => $product->cost_price,
            'quantity' => $product->inventory->quantity ?? 0,
            'is_active' => $product->is_active,
            'is_featured' => $product->is_featured,
            'sort_order' => $product->sort_order,
            'created_at' => $product->created_at,
            'brand' => $product->brand,
            'variants_count' => $product->variants_count,
            'images_count' => $product->images_count,
            'discounts' => $product->discounts->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'type' => $d->type,
                'value' => $d->value,
            ]),
            'categories' => $product->categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]),
        ]);

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'discounts' => Discount::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'brand_id', 'category_id', 'is_active', 'is_featured', 'discount_id', 'sort', 'direction']),
        ]);
    }

    public function show(Product $product): Response
    {
        $product->load([
            'brand',
            'categories',
            'categories.parent',
            'images',
            'variants',
            'variants.values.attribute',
            'inventory',
            'inventories.productVariant',
            'discounts',
        ]);

        return Inertia::render('admin/products/show', [
            'product' => $product,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/create', [
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'attributes' => Attribute::active()->with(['values' => fn ($q) => $q->active()])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'brand_id' => 'nullable|exists:brands,id',
            'type' => 'required|in:simple,variable',
            'category_ids' => 'array',
            'category_ids.*' => 'exists:categories,id',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'sku' => 'required|string|max:255|unique:products,sku',
            'barcode' => 'nullable|string|max:255',
            'price' => 'required_if:type,simple|nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer|min:0',
            'attribute_ids' => 'array',
            'attribute_ids.*' => 'exists:attributes,id',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);

        $categoryIds = $validated['category_ids'] ?? [];
        $attributeIds = $validated['attribute_ids'] ?? [];

        unset($validated['category_ids'], $validated['attribute_ids']);

        $productId = null;

        DB::transaction(function () use (&$validated, $categoryIds, $attributeIds, &$productId) {
            $product = Product::create($validated);
            $productId = $product->id;
            $product->categories()->sync($categoryIds);
            $product->productAttributes()->sync($attributeIds);
        });

        $product = Product::findOrFail($productId);

        if ($request->input('type') === 'simple') {
            $inventory = $this->inventoryService->getOrCreateForProduct($product);
            $this->inventoryService->setQuantity($inventory, $request->input('quantity', 0));
        }

        return to_route('admin.products.images.index', $productId);
    }

    public function edit(Product $product): Response
    {
        $product->load([
            'categories:id',
            'productAttributes:id',
            'inventory',
        ]);

        $categoryIds = $product->categories->pluck('id')->toArray();
        $attributeIds = $product->productAttributes->pluck('id')->toArray();

        return Inertia::render('admin/products/edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'brand_id' => $product->brand_id ? (string) $product->brand_id : '',
                'type' => $product->type,
                'category_ids' => $categoryIds,
                'attribute_ids' => $attributeIds,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'price' => $product->price,
                'compare_at_price' => $product->compare_at_price,
                'cost_price' => $product->cost_price,
                'quantity' => $product->inventory->quantity ?? 0,
                'is_active' => $product->is_active,
                'is_featured' => $product->is_featured,
                'sort_order' => $product->sort_order,
            ],
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'attributes' => Attribute::active()->with(['values' => fn ($q) => $q->active()])->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug,'.$product->id,
            'brand_id' => 'nullable|exists:brands,id',
            'type' => 'required|in:simple,variable',
            'category_ids' => 'array',
            'category_ids.*' => 'exists:categories,id',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'sku' => 'required|string|max:255|unique:products,sku,'.$product->id,
            'barcode' => 'nullable|string|max:255',
            'price' => 'required_if:type,simple|nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer|min:0',
            'attribute_ids' => 'array',
            'attribute_ids.*' => 'exists:attributes,id',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);

        $categoryIds = $validated['category_ids'] ?? [];
        $attributeIds = $validated['attribute_ids'] ?? [];

        unset($validated['category_ids'], $validated['attribute_ids']);

        DB::transaction(function () use (&$validated, $product, $categoryIds, $attributeIds) {
            $product->update($validated);
            $product->categories()->sync($categoryIds);
            $product->productAttributes()->sync($attributeIds);

            if ($validated['type'] !== 'variable') {
                $product->variants()->delete();
            }
        });

        if ($request->input('type') === 'simple' && $request->has('quantity')) {
            $inventory = $this->inventoryService->getOrCreateForProduct($product);
            $this->inventoryService->setQuantity($inventory, $request->input('quantity', 0));
        }

        return to_route('admin.products.index');
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return to_route('admin.products.index');
    }

    public function toggleFeatured(Product $product): RedirectResponse
    {
        $product->update(['is_featured' => ! $product->is_featured]);

        return to_route('admin.products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return to_route('admin.products.index');
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
