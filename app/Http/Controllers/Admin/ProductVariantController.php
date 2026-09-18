<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Support\AdminStoreContext;
use App\Support\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductVariantController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function index(Product $product): Response
    {
        $product->load([
            'variants' => fn ($q) => $q->with('values', 'inventory'),
            'productAttributes' => fn ($q) => $q->with('values'),
        ]);

        $variants = $product->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => $variant->price,
            'compare_at_price' => $variant->compare_at_price,
            'cost_price' => $variant->cost_price,
            'quantity' => $variant->inventory->quantity ?? 0,
            'is_active' => $variant->is_active,
            'attribute_value_ids' => $variant->values->pluck('id')->toArray(),
        ]);

        $attributes = $product->productAttributes->map(fn ($attr) => [
            'id' => $attr->id,
            'name' => $attr->name,
            'slug' => $attr->slug,
            'values' => $attr->values->map(fn ($val) => [
                'id' => $val->id,
                'value' => $val->value,
                'slug' => $val->slug,
            ]),
        ]);

        return Inertia::render('admin/products/variants', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'type' => $product->type,
                'price' => $product->price,
            ],
            'variants' => $variants,
            'attributes' => $attributes,
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        if (! $product->isVariable()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add variants to a simple product.',
            ], 422);
        }

        $storeId = app(CurrentStore::class)->scopeId();
        $targetStoreId = app(AdminStoreContext::class)->anchorStoreId($product);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:255', Rule::unique('product_variants', 'sku')->where('store_id', $storeId)],
            'barcode' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'attribute_value_ids' => 'required|array',
            'attribute_value_ids.*' => [app(AdminStoreContext::class)->existsInStore('attribute_values', $targetStoreId)],
        ]);

        $valueIds = $validated['attribute_value_ids'];
        unset($validated['attribute_value_ids']);

        $validated['product_id'] = $product->id;

        $variant = ProductVariant::create($validated);

        // Variants always live on their product's store, even when the
        // request resolved to another store.
        if ($product->store_id && $variant->store_id !== $product->store_id) {
            $variant->store_id = $product->store_id;
            $variant->save();
        }

        $variant->values()->sync($valueIds);

        $inventory = $this->inventoryService->getOrCreateForVariant($variant);
        $this->inventoryService->setQuantity($inventory, $request->input('quantity', 0));

        return response()->json([
            'success' => true,
            'message' => 'Variant created successfully.',
            'data' => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'price' => $variant->price,
                'compare_at_price' => $variant->compare_at_price,
                'cost_price' => $variant->cost_price,
                'quantity' => $variant->inventory->quantity ?? 0,
                'is_active' => $variant->is_active,
                'attribute_value_ids' => $valueIds,
            ],
        ]);
    }

    public function update(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        if ($variant->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Variant does not belong to this product.',
            ], 422);
        }

        $storeId = app(CurrentStore::class)->scopeId();
        $targetStoreId = app(AdminStoreContext::class)->anchorStoreId($product);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:255', Rule::unique('product_variants', 'sku')->ignore($variant->id)->where('store_id', $storeId)],
            'barcode' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'attribute_value_ids' => 'required|array',
            'attribute_value_ids.*' => [app(AdminStoreContext::class)->existsInStore('attribute_values', $targetStoreId)],
        ]);

        $valueIds = $validated['attribute_value_ids'];
        unset($validated['attribute_value_ids']);

        $variant->update($validated);
        $variant->values()->sync($valueIds);

        $inventory = $this->inventoryService->getOrCreateForVariant($variant);
        $this->inventoryService->setQuantity($inventory, $request->input('quantity', $inventory->quantity));

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully.',
        ]);
    }

    public function destroy(Product $product, ProductVariant $variant): JsonResponse
    {
        if ($variant->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Variant does not belong to this product.',
            ], 422);
        }

        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully.',
        ]);
    }

    public function generate(Request $request, Product $product): JsonResponse
    {
        if (! $product->isVariable()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot generate variants for a simple product.',
            ], 422);
        }

        $request->validate([
            'attribute_ids' => 'required|array',
            'attribute_ids.*' => [app(AdminStoreContext::class)->existsInStore('attributes', $product->store_id ?? app(CurrentStore::class)->scopeId())],
        ]);

        $attributes = Attribute::with(['values' => function ($q) use ($product) {
            if ($product->store_id !== null) {
                $q->where('attribute_values.store_id', $product->store_id);
            }
        }])->whereIn('id', $request->attribute_ids)->get();

        if ($attributes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid attributes provided.',
            ], 422);
        }

        $combinations = [[]];
        foreach ($attributes as $attr) {
            $next = [];
            foreach ($combinations as $combo) {
                foreach ($attr->values as $val) {
                    $next[] = [...$combo, $val->id];
                }
            }
            $combinations = $next;
        }

        $created = 0;
        DB::transaction(function () use ($combinations, $attributes, $product, &$created) {
            foreach ($combinations as $valueIds) {
                $nameParts = $attributes->map(fn ($attr) => $attr->values->first(fn ($val) => in_array($val->id, $valueIds))?->value ?? '')->toArray();

                $sku = strtoupper(preg_replace('/\s+/', '-', $product->sku)).'-'.collect($valueIds)->map(function ($id) use ($attributes) {
                    foreach ($attributes as $attr) {
                        $val = $attr->values->first(fn ($v) => $v->id === $id);
                        if ($val) {
                            return strtoupper(substr($val->slug, 0, 3));
                        }
                    }

                    return '';
                })->join('-');

                $variant = $product->variants()->create([
                    'name' => implode(' / ', $nameParts),
                    'sku' => $sku,
                    'price' => $product->price ?? 0,
                    'quantity' => 0,
                    'is_active' => true,
                ]);

                // Generated variants inherit the product's store even when
                // the request resolved elsewhere (platform-wide view).
                if ($product->store_id !== null && $variant->store_id !== $product->store_id) {
                    $variant->store_id = $product->store_id;
                    $variant->save();
                }

                $variant->values()->sync($valueIds);
                $this->inventoryService->getOrCreateForVariant($variant);
                $created++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "{$created} variant(s) generated successfully.",
        ]);
    }
}
