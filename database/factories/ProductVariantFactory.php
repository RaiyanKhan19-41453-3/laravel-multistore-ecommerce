<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'product_id' => Product::factory(),
            'name' => $name,
            'sku' => strtoupper(Str::random(3).'-'.Str::random(3)),
            'price' => fake()->randomFloat(2, 10, 500),
            'is_active' => true,
        ];
    }
}
