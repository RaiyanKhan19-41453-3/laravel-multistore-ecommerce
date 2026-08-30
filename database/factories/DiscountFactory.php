<?php

namespace Database\Factories;

use App\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Sale',
            'type' => fake()->randomElement(['percentage', 'fixed']),
            'value' => fake()->randomElement([10, 15, 20, 25, 50, 100]),
            'max_discount_amount' => null,
            'minimum_order_amount' => null,
            'starts_at' => null,
            'ends_at' => null,
            'usage_limit' => null,
            'usage_count' => 0,
            'is_active' => true,
            'priority' => 0,
            'stackable' => false,
        ];
    }

    public function percentage(): static
    {
        return $this->state(['type' => 'percentage']);
    }

    public function fixed(): static
    {
        return $this->state(['type' => 'fixed']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withMinOrder(float $amount): static
    {
        return $this->state(['minimum_order_amount' => $amount]);
    }

    public function withMaxDiscount(float $amount): static
    {
        return $this->state(['max_discount_amount' => $amount]);
    }

    public function withUsageLimit(int $limit): static
    {
        return $this->state(['usage_limit' => $limit]);
    }

    public function withPriority(int $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function stackable(): static
    {
        return $this->state(['stackable' => true]);
    }
}
