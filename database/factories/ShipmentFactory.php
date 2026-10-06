<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        $code = fake()->randomElement(array_keys(config('couriers', []) ?: ['other' => []]));

        return [
            'order_id' => Order::factory(),
            'courier_code' => $code,
            'courier' => config("couriers.{$code}.name", $code),
            'tracking_number' => strtoupper(fake()->bothify('??-#####')),
            'status' => 'pending',
            'note' => fake()->optional(0.3)->sentence(),
        ];
    }
}
