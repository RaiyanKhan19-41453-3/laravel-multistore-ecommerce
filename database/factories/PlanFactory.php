<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' Plan';

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'price' => fake()->randomElement([0, 9, 29, 99]),
            'currency' => 'USD',
            'interval' => 'monthly',
            'trial_days' => 14,
            'features' => ['products', 'orders'],
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
