<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\AdminStoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscountController extends Controller
{
    public function create(): Response
    {
        $products = Product::orderBy('name')->get(['id', 'name', 'sku']);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $brands = Brand::orderBy('name')->get(['id', 'name']);
        $variants = ProductVariant::with('product')->orderBy('name')->get(['id', 'name', 'sku', 'product_id']);

        return Inertia::render('admin/discounts/create', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'variants' => $variants,
        ]);
    }

    public function index(Request $request): Response
    {
        $query = Discount::with(['products', 'productVariants', 'categories', 'brands', 'coupons']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            $now = now();
            if ($filter === 'active') {
                $query->where('is_active', true)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                    });
            } elseif ($filter === 'expired') {
                $query->where('ends_at', '<', $now);
            } elseif ($filter === 'upcoming') {
                $query->where('starts_at', '>', $now);
            } elseif ($filter === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $discounts = $query->latest()->get();

        return Inertia::render('admin/discounts/index', [
            'discounts' => $discounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $adminStores = app(AdminStoreContext::class);
        $targetStoreId = $adminStores->anchorStoreId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:percentage,fixed',
            'value' => [
                'required',
                'numeric',
                'min:0',
                $request->input('type') === 'percentage' ? 'max:100' : '',
            ],
            'max_discount_amount' => 'nullable|numeric|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
            'stackable' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => [$adminStores->existsInStore('products', $targetStoreId)],
            'category_ids' => 'nullable|array',
            'category_ids.*' => [$adminStores->existsInStore('categories', $targetStoreId)],
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => [$adminStores->existsInStore('brands', $targetStoreId)],
            'variant_ids' => 'nullable|array',
            'variant_ids.*' => [$adminStores->existsInStore('product_variants', $targetStoreId)],
        ]);

        $productIds = $validated['product_ids'] ?? [];
        $categoryIds = $validated['category_ids'] ?? [];
        $brandIds = $validated['brand_ids'] ?? [];
        $variantIds = $validated['variant_ids'] ?? [];

        unset($validated['product_ids'], $validated['category_ids'], $validated['brand_ids'], $validated['variant_ids']);

        $discount = Discount::create($validated);

        if ($productIds) {
            $discount->products()->attach($productIds);
        }
        if ($categoryIds) {
            $discount->categories()->attach($categoryIds);
        }
        if ($brandIds) {
            $discount->brands()->attach($brandIds);
        }
        if ($variantIds) {
            $discount->productVariants()->attach($variantIds);
        }

        return to_route('admin.discounts.index');
    }

    public function show(Discount $discount): Response
    {
        $discount->load(['products', 'productVariants.product', 'categories', 'brands', 'coupons']);

        return Inertia::render('admin/discounts/show', [
            'discount' => $discount,
        ]);
    }

    public function edit(Discount $discount): Response
    {
        $discount->load(['products', 'productVariants.product', 'categories', 'brands']);

        $products = Product::orderBy('name')->get(['id', 'name', 'sku']);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $brands = Brand::orderBy('name')->get(['id', 'name']);
        $variants = ProductVariant::with('product')->orderBy('name')->get(['id', 'name', 'sku', 'product_id']);

        return Inertia::render('admin/discounts/edit', [
            'discount' => $discount,
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'variants' => $variants,
        ]);
    }

    public function update(Request $request, Discount $discount): RedirectResponse
    {
        $adminStores = app(AdminStoreContext::class);
        $targetStoreId = $adminStores->anchorStoreId($discount);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:percentage,fixed',
            'value' => [
                'required',
                'numeric',
                'min:0',
                $request->input('type') === 'percentage' ? 'max:100' : '',
            ],
            'max_discount_amount' => 'nullable|numeric|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
            'stackable' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => [$adminStores->existsInStore('products', $targetStoreId)],
            'category_ids' => 'nullable|array',
            'category_ids.*' => [$adminStores->existsInStore('categories', $targetStoreId)],
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => [$adminStores->existsInStore('brands', $targetStoreId)],
            'variant_ids' => 'nullable|array',
            'variant_ids.*' => [$adminStores->existsInStore('product_variants', $targetStoreId)],
        ]);

        $productIds = $validated['product_ids'] ?? [];
        $categoryIds = $validated['category_ids'] ?? [];
        $brandIds = $validated['brand_ids'] ?? [];
        $variantIds = $validated['variant_ids'] ?? [];

        unset($validated['product_ids'], $validated['category_ids'], $validated['brand_ids'], $validated['variant_ids']);

        $discount->update($validated);

        $discount->products()->sync($productIds);
        $discount->categories()->sync($categoryIds);
        $discount->brands()->sync($brandIds);
        $discount->productVariants()->sync($variantIds);

        return to_route('admin.discounts.index');
    }

    public function toggle(Discount $discount): RedirectResponse
    {
        $discount->update(['is_active' => ! $discount->is_active]);

        return to_route('admin.discounts.index');
    }

    public function destroy(Discount $discount): RedirectResponse
    {
        $discount->delete();

        return to_route('admin.discounts.index');
    }
}
