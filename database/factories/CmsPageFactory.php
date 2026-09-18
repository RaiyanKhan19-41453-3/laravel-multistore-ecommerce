<?php

namespace Database\Factories;

use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CmsPageFactory extends Factory
{
    protected $model = CmsPage::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'title_ar' => fake()->sentence(3),
            'slug' => Str::slug($title),
            'body' => fake()->paragraph(3),
            'body_ar' => fake()->paragraph(3),
            'meta_title' => $title,
            'meta_description' => fake()->sentence(8),
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
