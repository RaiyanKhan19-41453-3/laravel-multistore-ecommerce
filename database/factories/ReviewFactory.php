<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(1),
            'is_approved' => true,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['is_approved' => false]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['is_approved' => true, 'verified_purchase' => true]);
    }
}
