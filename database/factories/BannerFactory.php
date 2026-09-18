<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

class BannerFactory extends Factory
{
    protected $model = Banner::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'subtitle' => fake()->sentence(),
            'button_label' => 'Shop now',
            'button_link' => '/products',
            'layout' => 'double',
            'text_layout' => 'split',
            'show_title' => true,
            'show_subtitle' => true,
            'show_button' => true,
            'media_type' => 'image',
            'iframe_url' => null,
            'images' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
