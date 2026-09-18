<?php

namespace Database\Factories;

use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

class HeroSlideFactory extends Factory
{
    protected $model = HeroSlide::class;

    public function definition(): array
    {
        return [
            'eyebrow' => 'New season',
            'title' => fake()->words(4, true),
            'subtitle' => fake()->sentence(),
            'cta_label' => 'Shop now',
            'cta_link' => '/products',
            'image' => null,
            'layout' => 'split',
            'show_eyebrow' => true,
            'show_title' => true,
            'show_subtitle' => true,
            'show_button' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
