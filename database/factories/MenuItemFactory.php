<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(2, true),
            'title_ar' => null,
            'type' => 'url',
            'reference_id' => null,
            'url' => '/'.fake()->slug(),
            'click_behavior' => 'navigate',
            'display' => 'auto',
            'promo_image' => null,
            'promo_title' => null,
            'promo_link' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
