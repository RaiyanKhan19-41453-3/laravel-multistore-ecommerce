<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'discount_id' => Discount::factory(),
            'code' => strtoupper(fake()->bothify('????####')),
            'usage_limit' => null,
            'usage_count' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withUsageLimit(int $limit): static
    {
        return $this->state(['usage_limit' => $limit]);
    }
}
