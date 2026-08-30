<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'quantity' => fake()->numberBetween(0, 100),
            'reserved_quantity' => 0,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state([
            'product_id' => $product->id,
            'product_variant_id' => null,
        ]);
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
        ]);
    }

    public function withQuantity(int $quantity): static
    {
        return $this->state([
            'quantity' => $quantity,
        ]);
    }
}
