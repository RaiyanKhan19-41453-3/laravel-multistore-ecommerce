<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Support\CurrentStore;
use Illuminate\Database\Seeder;

/**
 * Demo homepage banners showcasing all three image layouts.
 * Idempotent: safe to re-run.
 *
 * Run with: php artisan db:seed --class=BannerSeeder
 */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $storeId = app(CurrentStore::class)->scopeId();

        $banners = [
            [
                'title' => 'New season essentials',
                'subtitle' => 'Fresh picks across clothing, shoes, and accessories, curated for the week.',
                'button_label' => 'Shop all',
                'button_link' => '/products',
                'layout' => 'double',
                'text_layout' => 'split',
                'images' => [
                    'https://picsum.photos/seed/banner-essentials-1/800/600',
                    'https://picsum.photos/seed/banner-essentials-2/800/600',
                ],
                'sort_order' => 0,
            ],
            [
                'title' => 'Steal the spotlight',
                'subtitle' => 'Bold pieces, best prices. Four favorites our shoppers keep reordering.',
                'button_label' => 'Explore',
                'button_link' => '/products',
                'layout' => 'quad',
                'text_layout' => 'center',
                'images' => [
                    'https://picsum.photos/seed/banner-spot-1/600/600',
                    'https://picsum.photos/seed/banner-spot-2/600/600',
                    'https://picsum.photos/seed/banner-spot-3/600/600',
                    'https://picsum.photos/seed/banner-spot-4/600/600',
                ],
                'sort_order' => 1,
            ],
        ];

        foreach ($banners as $banner) {
            Banner::updateOrCreate(
                ['store_id' => $storeId, 'title' => $banner['title']],
                array_merge($banner, ['store_id' => $storeId, 'is_active' => true])
            );
        }
    }
}
