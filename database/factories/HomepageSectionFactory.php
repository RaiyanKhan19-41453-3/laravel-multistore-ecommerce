<?php

namespace Database\Factories;

use App\Models\HomepageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class HomepageSectionFactory extends Factory
{
    protected $model = HomepageSection::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->randomElement(HomepageSection::KEYS),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
