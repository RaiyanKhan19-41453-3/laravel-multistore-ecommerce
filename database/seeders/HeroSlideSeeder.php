<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Support\CurrentStore;
use Illuminate\Database\Seeder;

/**
 * Demo hero slides showcasing the slider variant.
 * Idempotent: safe to re-run.
 *
 * Run with: php artisan db:seed --class=HeroSlideSeeder
 */
class HeroSlideSeeder extends Seeder
{
    public function run(): void
    {
        $storeId = app(CurrentStore::class)->scopeId();

        $slides = [
            [
                'eyebrow' => 'New season collection',
                'title' => 'Fresh styles have landed',
                'subtitle' => 'Discover the latest drops from your favorite brands, ready to ship today.',
                'cta_label' => 'Shop now',
                'cta_link' => '/products',
                'image' => 'https://picsum.photos/seed/hero-fresh/1600/900',
                'sort_order' => 0,
            ],
            [
                'eyebrow' => 'Limited time',
                'title' => 'Sale up to 50% off',
                'subtitle' => 'Discounts across selected products, brands, and categories. While stocks last.',
                'cta_label' => 'Shop the sale',
                'cta_link' => '/products',
                'image' => 'https://picsum.photos/seed/hero-sale/1600/900',
                'sort_order' => 1,
            ],
        ];

        foreach ($slides as $slide) {
            HeroSlide::updateOrCreate(
                ['store_id' => $storeId, 'title' => $slide['title']],
                array_merge($slide, ['store_id' => $storeId, 'is_active' => true])
            );
        }
    }
}
