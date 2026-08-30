<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => fake()->randomElement(['bkash', 'nagad', 'rocket', 'card', 'cod']),
            'status' => 'pending',
            'amount' => fake()->randomFloat(2, 100, 10000),
            'gateway' => 'sslcommerz',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_at' => now(),
            'gateway_transaction_id' => 'TXN-'.strtoupper(fake()->bothify('??????')),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed']);
    }

    public function cod(): static
    {
        return $this->state(fn () => ['method' => 'cod']);
    }

    public function bkash(): static
    {
        return $this->state(fn () => ['method' => 'bkash']);
    }
}
