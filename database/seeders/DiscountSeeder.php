<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Summer Sale: 20% off Shoes category, max ৳500 discount, min order ৳200
        $summerSale = Discount::updateOrCreate(
            ['name' => 'Summer Sale'],
            [
                'type' => 'percentage',
                'value' => 20,
                'max_discount_amount' => 500,
                'minimum_order_amount' => 200,
                'starts_at' => now()->subDays(30),
                'ends_at' => now()->addDays(30),
                'usage_limit' => null,
                'usage_count' => 47,
                'is_active' => true,
                'priority' => 5,
                'stackable' => false,
            ]
        );
        $this->attachCategories($summerSale, ['shoes']);

        // 2. Eid Mega Sale: ৳500 off on orders over ৳3000
        $eidSale = Discount::updateOrCreate(
            ['name' => 'Eid Mega Sale'],
            [
                'type' => 'fixed',
                'value' => 500,
                'max_discount_amount' => null,
                'minimum_order_amount' => 3000,
                'starts_at' => now()->subDays(10),
                'ends_at' => now()->addDays(15),
                'usage_limit' => 200,
                'usage_count' => 83,
                'is_active' => true,
                'priority' => 10,
                'stackable' => false,
            ]
        );
        $this->attachCategories($eidSale, ['clothing', 'pants', 'shoes']);

        // 3. Nike Brand Discount: 15% off all Nike products
        $nikeDiscount = Discount::updateOrCreate(
            ['name' => 'Nike Brand Discount'],
            [
                'type' => 'percentage',
                'value' => 15,
                'max_discount_amount' => null,
                'minimum_order_amount' => null,
                'starts_at' => null,
                'ends_at' => null,
                'usage_limit' => null,
                'usage_count' => 122,
                'is_active' => true,
                'priority' => 3,
                'stackable' => true,
            ]
        );
        $this->attachBrands($nikeDiscount, ['nike']);

        // 4. New Customer Welcome: 10% off first order, coupon-only (not auto-applied)
        $welcomeDiscount = Discount::updateOrCreate(
            ['name' => 'New Customer Welcome'],
            [
                'type' => 'percentage',
                'value' => 10,
                'max_discount_amount' => 300,
                'minimum_order_amount' => null,
                'starts_at' => null,
                'ends_at' => null,
                'usage_limit' => null,
                'usage_count' => 0,
                'is_active' => true,
                'priority' => 1,
                'stackable' => true,
                'coupon_only' => true,
            ]
        );

        // 5. Flash Sale: 50% off specific products
        $flashSale = Discount::updateOrCreate(
            ['name' => 'Flash Sale: 50% Off'],
            [
                'type' => 'percentage',
                'value' => 50,
                'max_discount_amount' => 200,
                'minimum_order_amount' => null,
                'starts_at' => now()->subHours(2),
                'ends_at' => now()->addHours(6),
                'usage_limit' => 50,
                'usage_count' => 31,
                'is_active' => true,
                'priority' => 20,
                'stackable' => false,
            ]
        );
        $this->attachProducts($flashSale, ['NIK-TS', 'ADI-HD']);

        // 6. Bata Clearance: 30% off Bata brand
        $bataClearance = Discount::updateOrCreate(
            ['name' => 'Bata Clearance'],
            [
                'type' => 'percentage',
                'value' => 30,
                'max_discount_amount' => null,
                'minimum_order_amount' => null,
                'starts_at' => now()->subDays(15),
                'ends_at' => now()->addDays(5),
                'usage_limit' => 100,
                'usage_count' => 67,
                'is_active' => true,
                'priority' => 7,
                'stackable' => false,
            ]
        );
        $this->attachBrands($bataClearance, ['bata']);

        // 7. Inactive/Expired discount
        Discount::updateOrCreate(
            ['name' => 'Black Friday 2025'],
            [
                'type' => 'percentage',
                'value' => 40,
                'max_discount_amount' => 1000,
                'minimum_order_amount' => 5000,
                'starts_at' => now()->subMonths(8),
                'ends_at' => now()->subMonths(7),
                'usage_limit' => 500,
                'usage_count' => 489,
                'is_active' => false,
                'priority' => 0,
                'stackable' => false,
            ]
        );

        // 8. Upcoming discount
        Discount::updateOrCreate(
            ['name' => 'Winter Collection Launch'],
            [
                'type' => 'fixed',
                'value' => 300,
                'max_discount_amount' => null,
                'minimum_order_amount' => 2000,
                'starts_at' => now()->addMonth(),
                'ends_at' => now()->addMonth()->addDays(30),
                'usage_limit' => null,
                'usage_count' => 0,
                'is_active' => true,
                'priority' => 0,
                'stackable' => false,
            ]
        );

        // === Coupons ===

        Coupon::updateOrCreate(
            ['code' => 'SUMMER20'],
            [
                'discount_id' => $summerSale->id,
                'usage_limit' => 100,
                'usage_count' => 12,
                'starts_at' => now()->subDays(30),
                'ends_at' => now()->addDays(30),
                'is_active' => true,
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'EID500'],
            [
                'discount_id' => $eidSale->id,
                'usage_limit' => 200,
                'usage_count' => 45,
                'starts_at' => now()->subDays(10),
                'ends_at' => now()->addDays(15),
                'is_active' => true,
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'WELCOME10'],
            [
                'discount_id' => $welcomeDiscount->id,
                'usage_limit' => null,
                'usage_count' => 0,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'FLASH50'],
            [
                'discount_id' => $flashSale->id,
                'usage_limit' => 50,
                'usage_count' => 18,
                'starts_at' => now()->subHours(2),
                'ends_at' => now()->addHours(6),
                'is_active' => true,
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'OLDCOUPON'],
            [
                'discount_id' => $summerSale->id,
                'usage_limit' => 50,
                'usage_count' => 50,
                'starts_at' => now()->subMonths(3),
                'ends_at' => now()->subMonths(2),
                'is_active' => false,
            ]
        );
    }

    private function attachCategories(Discount $discount, array $slugs): void
    {
        $ids = Category::whereIn('slug', $slugs)->pluck('id')->toArray();

        if (! empty($ids)) {
            $discount->categories()->syncWithoutDetaching($ids);
        }
    }

    private function attachBrands(Discount $discount, array $slugs): void
    {
        $ids = Brand::whereIn('slug', $slugs)->pluck('id')->toArray();

        if (! empty($ids)) {
            $discount->brands()->syncWithoutDetaching($ids);
        }
    }

    private function attachProducts(Discount $discount, array $skus): void
    {
        $ids = Product::whereIn('sku', $skus)->pluck('id')->toArray();

        if (! empty($ids)) {
            $discount->products()->syncWithoutDetaching($ids);
        }
    }
}
