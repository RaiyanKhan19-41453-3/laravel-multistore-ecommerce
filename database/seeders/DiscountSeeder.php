<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Discount;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Summer Sale — 20% off Shoes category, max ৳500 discount, min order ৳200
        $summerSale = Discount::create([
            'name' => 'Summer Sale',
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
        ]);
        $summerSale->categories()->attach(6); // Shoes

        // 2. Eid Mega Sale — ৳500 off on orders over ৳3000
        $eidSale = Discount::create([
            'name' => 'Eid Mega Sale',
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
        ]);
        $eidSale->categories()->attach([1, 5, 6]); // Clothing, Pants, Shoes

        // 3. Nike Brand Discount — 15% off all Nike products
        $nikeDiscount = Discount::create([
            'name' => 'Nike Brand Discount',
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
        ]);
        $nikeDiscount->brands()->attach(1); // Nike

        // 4. New Customer Welcome — 10% off first order, coupon-only (not auto-applied)
        $welcomeDiscount = Discount::create([
            'name' => 'New Customer Welcome',
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
        ]);

        // 5. Flash Sale — 50% off specific products
        $flashSale = Discount::create([
            'name' => 'Flash Sale — 50% Off',
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
        ]);
        $flashSale->products()->attach([5, 6]); // Nike T-Shirt, Adidas Hoodie

        // 6. Bata Clearance — 30% off Bata brand
        $bataClearance = Discount::create([
            'name' => 'Bata Clearance',
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
        ]);
        $bataClearance->brands()->attach(3); // Bata

        // 7. Inactive/Expired discount
        Discount::create([
            'name' => 'Black Friday 2025',
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
        ]);

        // 8. Upcoming discount
        Discount::create([
            'name' => 'Winter Collection Launch',
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
        ]);

        // === Coupons ===

        // SUMMER20 — for the Summer Sale
        Coupon::create([
            'discount_id' => $summerSale->id,
            'code' => 'SUMMER20',
            'usage_limit' => 100,
            'usage_count' => 12,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        // EID500 — for the Eid Mega Sale
        Coupon::create([
            'discount_id' => $eidSale->id,
            'code' => 'EID500',
            'usage_limit' => 200,
            'usage_count' => 45,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(15),
            'is_active' => true,
        ]);

        // WELCOME10 — for New Customer Welcome
        Coupon::create([
            'discount_id' => $welcomeDiscount->id,
            'code' => 'WELCOME10',
            'usage_limit' => null,
            'usage_count' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ]);

        // FLASH50 — for Flash Sale
        Coupon::create([
            'discount_id' => $flashSale->id,
            'code' => 'FLASH50',
            'usage_limit' => 50,
            'usage_count' => 18,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->addHours(6),
            'is_active' => true,
        ]);

        // OLDcoupon — expired coupon
        Coupon::create([
            'discount_id' => $summerSale->id,
            'code' => 'OLDCOUPON',
            'usage_limit' => 50,
            'usage_count' => 50,
            'starts_at' => now()->subMonths(3),
            'ends_at' => now()->subMonths(2),
            'is_active' => false,
        ]);
    }
}
