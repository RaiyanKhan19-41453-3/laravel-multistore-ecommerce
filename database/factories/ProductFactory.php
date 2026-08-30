<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => 'simple',
            'description' => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'sku' => strtoupper(Str::random(3).'-'.Str::random(3)),
            'price' => fake()->randomFloat(2, 10, 500),
            'is_active' => true,
            'is_featured' => fake()->boolean(30),
            'sort_order' => 0,
        ];
    }
}
