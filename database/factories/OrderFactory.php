<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 10000);
        $discountTotal = 0;

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(fake()->unique()->bothify('??????')),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'shipping_cost' => 0,
            'tax_amount' => 0,
            'total' => round($subtotal - $discountTotal, 2),
            'shipping_name' => fake()->name(),
            'shipping_phone' => fake()->phoneNumber(),
            'shipping_address' => fake()->address(),
            'shipping_city' => fake()->city(),
            'shipping_state' => fake()->state(),
            'shipping_country' => 'Bangladesh',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed', 'paid_at' => now()]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => 'processing', 'paid_at' => now()]);
    }

    public function shipped(): static
    {
        return $this->state(fn () => ['status' => 'shipped', 'paid_at' => now(), 'shipped_at' => now()]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['status' => 'delivered', 'paid_at' => now(), 'shipped_at' => now()->subDays(2), 'delivered_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'paid_at' => now(), 'shipped_at' => now()->subDays(5), 'delivered_at' => now()->subDay()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['status' => 'expired', 'expires_at' => now()->subMinute()]);
    }

    public function withCoupon(string $code): static
    {
        return $this->state(fn () => ['coupon_code' => $code]);
    }
}
