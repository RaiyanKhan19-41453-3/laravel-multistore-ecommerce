<?php

namespace Database\Factories;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'courier_id' => Courier::factory(),
            'courier' => fake()->randomElement(['Pathao', 'Paperfly', 'SA Paribahan', 'Sundarban', 'Ecourier']),
            'tracking_number' => strtoupper(fake()->bothify('??-#####')),
            'status' => 'pending',
            'note' => fake()->optional(0.3)->sentence(),
        ];
    }
}
