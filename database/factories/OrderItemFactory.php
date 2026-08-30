<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 100, 5000);
        $quantity = fake()->numberBetween(1, 5);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->bothify('???-####')),
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => round($unitPrice * $quantity, 2),
            'discount_amount' => 0,
            'total' => round($unitPrice * $quantity, 2),
        ];
    }
}
