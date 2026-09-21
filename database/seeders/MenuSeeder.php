<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\MenuItem;
use App\Support\CurrentStore;
use Illuminate\Database\Seeder;

/**
 * Demo storefront navigation: one mega menu, one small dropdown,
 * plus plain links. Idempotent: safe to re-run.
 *
 * Run with: php artisan db:seed --class=MenuSeeder
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $storeId = app(CurrentStore::class)->scopeId();

        $item = fn (array $attrs, ?int $parentId = null): MenuItem => MenuItem::updateOrCreate(
            [
                'title' => $attrs['title'],
                'parent_id' => $parentId,
                'store_id' => $storeId,
            ],
            array_merge($attrs, ['parent_id' => $parentId, 'store_id' => $storeId])
        );

        $item([
            'title' => 'Home',
            'title_ar' => 'الرئيسية',
            'type' => 'url',
            'url' => '/',
            'click_behavior' => 'navigate',
            'display' => 'auto',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $shop = $item([
            'title' => 'Shop',
            'title_ar' => 'تسوّق',
            'type' => 'url',
            'url' => '/products',
            'click_behavior' => 'expand',
            'display' => 'mega',
            'promo_title' => 'New Season Sale',
            'promo_image' => 'https://picsum.photos/seed/menu-sale/480/600',
            'promo_link' => '/products',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $categories = Category::active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(4)
            ->get();

        if ($categories->isEmpty()) {
            $item([
                'title' => 'All Products',
                'title_ar' => 'كل المنتجات',
                'type' => 'url',
                'url' => '/products',
                'click_behavior' => 'navigate',
                'display' => 'auto',
                'is_active' => true,
                'sort_order' => 0,
            ], $shop->id);
        }

        foreach ($categories as $i => $category) {
            $item([
                'title' => $category->name,
                'type' => 'category',
                'reference_id' => $category->id,
                'click_behavior' => 'navigate',
                'display' => 'auto',
                'is_active' => true,
                'sort_order' => $i,
            ], $shop->id);
        }

        $brands = Brand::active()->orderBy('name')->limit(4)->get();

        if ($brands->isNotEmpty()) {
            $brandsParent = $item([
                'title' => 'Brands',
                'title_ar' => 'العلامات التجارية',
                'type' => 'url',
                'url' => '/products',
                'click_behavior' => 'expand',
                'display' => 'dropdown',
                'is_active' => true,
                'sort_order' => 2,
            ]);

            foreach ($brands as $i => $brand) {
                $item([
                    'title' => $brand->name,
                    'type' => 'brand',
                    'reference_id' => $brand->id,
                    'click_behavior' => 'navigate',
                    'display' => 'auto',
                    'is_active' => true,
                    'sort_order' => $i,
                ], $brandsParent->id);
            }
        }

        $page = CmsPage::published()->orderBy('title')->first();

        if ($page) {
            $item([
                'title' => $page->title,
                'type' => 'page',
                'reference_id' => $page->id,
                'click_behavior' => 'navigate',
                'display' => 'auto',
                'is_active' => true,
                'sort_order' => 3,
            ]);
        }

        $item([
            'title' => 'Sale',
            'title_ar' => 'تخفيضات',
            'type' => 'url',
            'url' => '/products',
            'click_behavior' => 'navigate',
            'display' => 'auto',
            'is_active' => true,
            'sort_order' => 4,
        ]);
    }
}
