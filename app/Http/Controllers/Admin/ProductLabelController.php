<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductLabelController extends Controller
{
    public function __construct(
        protected BarcodeService $barcodes,
    ) {}

    /**
     * Label picker: every product with its variants, prices and codes.
     */
    public function index(): Response
    {
        $products = Product::with(['variants' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'price' => (float) $product->price,
                'type' => $product->type,
                'printable' => $this->barcodes->resolveValue($product) !== null || $product->isVariable(),
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'price' => (float) ($variant->price ?? $product->price),
                    'printable' => $this->barcodes->resolveValue($variant) !== null,
                ])->values(),
            ]);

        return Inertia::render('admin/products/labels', [
            'products' => $products,
            'layouts' => config('barcodes.layouts'),
            'defaultLayout' => config('barcodes.default_layout'),
        ]);
    }

    /**
     * Print sheet. ?items=p:1:3,v:5:2 (type:id:qty) &layout=a4-40
     */
    public function print(Request $request): Response
    {
        $validated = $request->validate([
            'items' => 'required|string|max:2000',
            'layout' => 'nullable|string|in:'.implode(',', array_keys(config('barcodes.layouts'))),
        ]);

        $layout = $validated['layout'] ?? config('barcodes.default_layout');
        $maxLabels = config('barcodes.max_labels', 500);

        $productQty = [];
        $variantQty = [];
        $skipped = [];

        foreach (explode(',', $validated['items']) as $chunk) {
            $parts = explode(':', trim($chunk));

            if (count($parts) !== 3 || ! in_array($parts[0], ['p', 'v']) || ! ctype_digit($parts[1])) {
                continue;
            }

            $qty = max(1, min(100, (int) $parts[2]));

            if ($parts[0] === 'p') {
                $productQty[(int) $parts[1]] = $qty;
            } else {
                $variantQty[(int) $parts[1]] = $qty;
            }
        }

        $labels = [];

        if (! empty($productQty)) {
            $products = Product::whereIn('id', array_keys($productQty))->get()->keyBy('id');

            foreach ($productQty as $id => $qty) {
                $product = $products->get($id);

                if (! $product) {
                    $skipped[] = "Product #{$id} not found.";

                    continue;
                }

                if ($product->isVariable()) {
                    $skipped[] = "{$product->name}: variable product — pick its variants instead.";

                    continue;
                }

                if ($this->barcodes->resolveValue($product) === null) {
                    $skipped[] = "{$product->name}: no SKU or barcode.";

                    continue;
                }

                $labels = array_merge($labels, $this->barcodes->labelsForProducts(collect([$product]), $qty));
            }
        }

        if (! empty($variantQty)) {
            $variants = ProductVariant::with('product')->whereIn('id', array_keys($variantQty))->get()->keyBy('id');

            foreach ($variantQty as $id => $qty) {
                $variant = $variants->get($id);

                if (! $variant) {
                    $skipped[] = "Variant #{$id} not found.";

                    continue;
                }

                if ($this->barcodes->resolveValue($variant) === null) {
                    $skipped[] = "{$variant->name}: no SKU or barcode.";

                    continue;
                }

                $labels = array_merge($labels, $this->barcodes->labelsForVariants(collect([$variant]), $qty));
            }
        }

        $truncated = false;

        if (count($labels) > $maxLabels) {
            $labels = array_slice($labels, 0, $maxLabels);
            $truncated = true;
        }

        return Inertia::render('admin/products/label-sheet', [
            'labels' => $labels,
            'layout' => $layout,
            'layoutConfig' => config("barcodes.layouts.{$layout}"),
            'skipped' => $skipped,
            'truncated' => $truncated,
            'maxLabels' => $maxLabels,
        ]);
    }
}
