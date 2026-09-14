<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    /** @var array<string, string> */
    private array $svgCache = [];

    /**
     * Resolve the scannable value for a product or variant.
     * Manual barcode wins; SKU is the automatic fallback.
     * Returns null when neither exists (caller must skip the label).
     */
    public function resolveValue(Product|ProductVariant $model): ?string
    {
        $value = trim((string) ($model->barcode ?? ''));

        if ($value === '') {
            $value = trim((string) ($model->sku ?? ''));
        }

        return $value === '' ? null : $value;
    }

    /**
     * Render a Code-128 barcode as inline SVG, sized for labels.
     * Identical values reuse the cached render within the request.
     */
    public function svg(string $value, float $scale = 1.0): string
    {
        $key = $value."\0".$scale;

        if (! isset($this->svgCache[$key])) {
            $generator = new BarcodeGeneratorSVG;
            $this->svgCache[$key] = $generator->getBarcode($value, $generator::TYPE_CODE_128, 2 * $scale, (int) (48 * $scale));
        }

        return $this->svgCache[$key];
    }

    /**
     * Build print-ready label payloads for products (simple ones only;
     * variable products are represented by their variants).
     *
     * @return array<int, array{name: string, variant: ?string, sku: string, price: float, code: string, svg: string}>
     */
    public function labelsForProducts(Collection $products, int $quantity = 1): array
    {
        $labels = [];

        foreach ($products as $product) {
            if ($product->isVariable()) {
                continue;
            }

            $code = $this->resolveValue($product);

            if ($code === null) {
                continue;
            }

            for ($i = 0; $i < $quantity; $i++) {
                $labels[] = $this->makeLabel(
                    $product->name,
                    null,
                    $product->sku ?? $code,
                    (float) $product->price,
                    $code,
                );
            }
        }

        return $labels;
    }

    /**
     * Build print-ready label payloads for variants.
     *
     * @return array<int, array{name: string, variant: ?string, sku: string, price: float, code: string, svg: string}>
     */
    public function labelsForVariants(Collection $variants, int $quantity = 1): array
    {
        $labels = [];

        if ($variants instanceof \Illuminate\Database\Eloquent\Collection) {
            $variants->loadMissing('product');
        } else {
            $variants->each(fn (ProductVariant $variant) => $variant->loadMissing('product'));
        }

        foreach ($variants as $variant) {
            $code = $this->resolveValue($variant);

            if ($code === null) {
                continue;
            }

            for ($i = 0; $i < $quantity; $i++) {
                $labels[] = $this->makeLabel(
                    $variant->product?->name ?? $variant->name,
                    $variant->name,
                    $variant->sku ?? $code,
                    (float) ($variant->price ?? $variant->product?->price ?? 0),
                    $code,
                );
            }
        }

        return $labels;
    }

    /**
     * @return array{name: string, variant: ?string, sku: string, price: float, code: string, svg: string}
     */
    private function makeLabel(string $name, ?string $variant, string $sku, float $price, string $code): array
    {
        return [
            'name' => $name,
            'variant' => $variant,
            'sku' => $sku,
            'price' => $price,
            'code' => $code,
            'svg' => $this->svg($code),
        ];
    }
}
