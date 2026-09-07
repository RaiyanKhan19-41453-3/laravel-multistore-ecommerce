<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $brands = Brand::all();
        $categories = Category::all();
        $colorAttr = Attribute::where('name', 'Color')->first();
        $sizeAttr = Attribute::where('name', 'Size')->first();

        // Simple products
        $simpleProducts = [
            [
                'name' => 'Nike Air Max 270',
                'brand_id' => $brands->firstWhere('name', 'Nike')?->id ?? $brands->first()->id,
                'description' => 'The Nike Air Max 270 features Nike\'s biggest heel Air unit yet for a super-soft ride.',
                'short_description' => 'Iconic lifestyle shoe with Max Air unit',
                'sku' => 'NIK-AM270',
                'price' => 150.00,
                'compare_at_price' => 180.00,
                'cost_price' => 65.00,
                'quantity' => 45,
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Adidas Ultraboost 22',
                'brand_id' => $brands->firstWhere('name', 'Adidas')?->id ?? $brands->first()->id,
                'description' => 'Running shoes with responsive BOOST midsole and Primeknit+ upper.',
                'short_description' => 'Responsive running shoes with BOOST',
                'sku' => 'ADI-UB22',
                'price' => 190.00,
                'compare_at_price' => null,
                'cost_price' => 80.00,
                'quantity' => 30,
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Adidas Stan Smith',
                'brand_id' => $brands->firstWhere('name', 'Adidas')?->id ?? $brands->first()->id,
                'description' => 'The Stan Smith is a true classic. Originally designed for tennis, now a streetwear icon.',
                'short_description' => 'Iconic streetwear sneakers',
                'sku' => 'ADI-SS',
                'price' => 100.00,
                'compare_at_price' => null,
                'cost_price' => 40.00,
                'quantity' => 75,
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Bata Men Formal Oxfords',
                'brand_id' => $brands->firstWhere('name', 'Bata')?->id ?? $brands->last()->id,
                'description' => 'Classic Oxford dress shoes crafted from premium leather.',
                'short_description' => 'Premium leather formal shoes',
                'sku' => 'BTA-OXF',
                'price' => 89.99,
                'compare_at_price' => 109.99,
                'cost_price' => 35.00,
                'quantity' => 60,
                'is_active' => true,
                'is_featured' => false,
            ],
        ];

        foreach ($simpleProducts as $productData) {
            $quantity = $productData['quantity'];
            unset($productData['quantity']);
            $product = Product::firstOrCreate(
                ['sku' => $productData['sku']],
                array_merge($productData, ['type' => 'simple'])
            );
            $categoryIds = $categories->random(min(2, $categories->count()))->pluck('id')->toArray();
            $product->categories()->syncWithoutDetaching($categoryIds);
            $product->inventory()->firstOrCreate(
                ['product_variant_id' => null],
                ['quantity' => $quantity]
            );
        }

        // Variable products with variants
        if ($colorAttr && $sizeAttr) {
            $colorValues = $colorAttr->values()->get();
            $sizeValues = $sizeAttr->values()->get();

            $variableProducts = [
                [
                    'name' => 'Nike T-Shirt Classic',
                    'brand_id' => $brands->firstWhere('name', 'Nike')?->id ?? $brands->first()->id,
                    'description' => 'Classic cotton t-shirt with iconic Nike swoosh.',
                    'short_description' => 'Classic Nike cotton tee',
                    'sku' => 'NIK-TS',
                    'price' => 35.00,
                    'compare_at_price' => null,
                    'cost_price' => 12.00,
                    'is_active' => true,
                    'is_featured' => true,
                    'attributes' => ['Color', 'Size'],
                ],
                [
                    'name' => 'Adidas Hoodie Essentials',
                    'brand_id' => $brands->firstWhere('name', 'Adidas')?->id ?? $brands->first()->id,
                    'description' => 'Comfortable pullover hoodie with kangaroo pocket.',
                    'short_description' => 'Essential Adidas hoodie',
                    'sku' => 'ADI-HD',
                    'price' => 65.00,
                    'compare_at_price' => 80.00,
                    'cost_price' => 25.00,
                    'is_active' => true,
                    'is_featured' => false,
                    'attributes' => ['Color', 'Size'],
                ],
                [
                    'name' => 'Bata Canvas Sneakers',
                    'brand_id' => $brands->firstWhere('name', 'Bata')?->id ?? $brands->last()->id,
                    'description' => 'Lightweight canvas sneakers for casual wear.',
                    'short_description' => 'Casual canvas sneakers',
                    'sku' => 'BTA-CVS',
                    'price' => 45.00,
                    'compare_at_price' => null,
                    'cost_price' => 15.00,
                    'is_active' => true,
                    'is_featured' => false,
                    'attributes' => ['Color', 'Size'],
                ],
            ];

            $selectedColors = $colorValues->take(4);
            $selectedSizes = $sizeValues->take(4);

            foreach ($variableProducts as $productData) {
                $attributeNames = $productData['attributes'];
                unset($productData['attributes']);

                $product = Product::firstOrCreate(
                    ['sku' => $productData['sku']],
                    array_merge($productData, ['type' => 'variable'])
                );

                $categoryIds = $categories->random(min(2, $categories->count()))->pluck('id')->toArray();
                $product->categories()->syncWithoutDetaching($categoryIds);

                $productAttributes = Attribute::whereIn('name', $attributeNames)->get();
                $product->productAttributes()->syncWithoutDetaching($productAttributes->pluck('id')->mapWithKeys(fn ($id) => [$id => ['sort_order' => 0]]));

                $colors = $selectedColors;
                $sizes = $selectedSizes;

                foreach ($colors as $color) {
                    foreach ($sizes as $size) {
                        $variantSku = strtoupper($product['sku'].'-'.$color->slug.'-'.$size->slug);

                        $qty = rand(5, 50);
                        $variant = $product->variants()->firstOrCreate(
                            ['sku' => $variantSku],
                            [
                                'name' => $color->value.' / '.$size->value,
                                'price' => $product['price'],
                                'compare_at_price' => $product['compare_at_price'],
                                'cost_price' => $product['cost_price'],
                                'is_active' => true,
                            ]
                        );

                        $variant->inventory()->firstOrCreate(
                            [],
                            [
                                'product_id' => $product->id,
                                'quantity' => $qty,
                            ]
                        );
                        $variant->values()->syncWithoutDetaching([$color->id, $size->id]);
                    }
                }
            }
        }
    }
}
