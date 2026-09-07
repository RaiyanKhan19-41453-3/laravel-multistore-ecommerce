<?php

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Product;
use App\Services\DiscountService;

it('calculates percentage discount correctly', function () {
    $discount = Discount::factory()->percentage()->create(['value' => 20]);
    $service = new DiscountService;

    $result = $service->calculateDiscount($discount, 1000);

    $this->assertEquals(200, $result);
});

it('calculates fixed discount correctly', function () {
    $discount = Discount::factory()->fixed()->create(['value' => 500]);
    $service = new DiscountService;

    $result = $service->calculateDiscount($discount, 1000);

    $this->assertEquals(500, $result);
});

it('caps discount at max_discount_amount', function () {
    $discount = Discount::factory()->percentage()->create([
        'value' => 50,
        'max_discount_amount' => 100,
    ]);
    $service = new DiscountService;

    $result = $service->calculateDiscount($discount, 1000);

    $this->assertEquals(100, $result);
});

it('returns zero when below minimum order amount', function () {
    $discount = Discount::factory()->fixed()->create([
        'value' => 500,
        'minimum_order_amount' => 3000,
    ]);
    $service = new DiscountService;

    $result = $service->calculateDiscount($discount, 2000);

    $this->assertEquals(0, $result);
});

it('returns zero when discount is inactive', function () {
    $discount = Discount::factory()->inactive()->create(['value' => 20]);
    $service = new DiscountService;

    $result = $service->calculateDiscount($discount, 1000);

    $this->assertEquals(0, $result);
});

it('returns zero when usage limit reached', function () {
    $discount = Discount::factory()->create([
        'value' => 20,
        'usage_limit' => 10,
        'usage_count' => 10,
    ]);
    $service = new DiscountService;

    $result = $service->calculateDiscount($discount, 1000);

    $this->assertEquals(0, $result);
});

it('applies valid coupon', function () {
    $discount = Discount::factory()->create(['is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'WELCOME500',
        'is_active' => true,
    ]);

    $service = new DiscountService;
    $result = $service->applyCoupon('WELCOME500');

    $this->assertNotNull($result);
    $this->assertEquals($coupon->id, $result->id);
});

it('rejects invalid coupon', function () {
    $service = new DiscountService;
    $result = $service->applyCoupon('INVALID');

    $this->assertNull($result);
});

it('rejects inactive coupon', function () {
    $discount = Discount::factory()->create(['is_active' => true]);
    Coupon::factory()->for($discount)->create([
        'code' => 'INACTIVE',
        'is_active' => false,
    ]);

    $service = new DiscountService;
    $result = $service->applyCoupon('INACTIVE');

    $this->assertNull($result);
});

it('finds best discount for product', function () {
    $product = Product::factory()->create(['price' => 1000]);

    $discount1 = Discount::factory()->percentage()->create(['value' => 10]);
    $discount1->products()->attach($product);

    $discount2 = Discount::factory()->percentage()->create(['value' => 25]);
    $discount2->products()->attach($product);

    $service = new DiscountService;
    $result = $service->bestDiscountForProduct($product, 1000);

    $this->assertNotNull($result);
    $this->assertEquals(250, $result['amount']);
    $this->assertEquals($discount2->id, $result['discount']->id);
});

it('respects priority when finding best discount', function () {
    $product = Product::factory()->create(['price' => 1000]);

    $discount1 = Discount::factory()->percentage()->create(['value' => 30, 'priority' => 1]);
    $discount1->products()->attach($product);

    $discount2 = Discount::factory()->percentage()->create(['value' => 20, 'priority' => 10]);
    $discount2->products()->attach($product);

    $service = new DiscountService;
    $result = $service->bestDiscountForProduct($product, 1000);

    $this->assertNotNull($result);
    $this->assertEquals($discount2->id, $result['discount']->id);
    $this->assertEquals(200, $result['amount']);
});

it('rejects coupon when parent discount is inactive', function () {
    $discount = Discount::factory()->inactive()->create();
    Coupon::factory()->for($discount)->create([
        'code' => 'EXPIRED',
        'is_active' => true,
    ]);

    $service = new DiscountService;
    $result = $service->applyCoupon('EXPIRED');

    $this->assertNull($result);
});

it('rejects coupon when parent discount usage limit reached', function () {
    $discount = Discount::factory()->create([
        'is_active' => true,
        'usage_limit' => 5,
        'usage_count' => 5,
    ]);
    Coupon::factory()->for($discount)->create([
        'code' => 'USEDUP',
        'is_active' => true,
    ]);

    $service = new DiscountService;
    $result = $service->applyCoupon('USEDUP');

    $this->assertNull($result);
});

it('increments usage atomically and respects limit', function () {
    $discount = Discount::factory()->create([
        'usage_limit' => 3,
        'usage_count' => 2,
    ]);
    $service = new DiscountService;

    $result = $service->incrementUsage($discount);

    $this->assertTrue($result);
    $discount->refresh();
    $this->assertEquals(3, $discount->usage_count);

    $result = $service->incrementUsage($discount);
    $this->assertFalse($result);
    $discount->refresh();
    $this->assertEquals(3, $discount->usage_count);
});

it('increments coupon usage atomically and respects limit', function () {

    $discount = Discount::factory()->create(['is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'usage_limit' => 2,
        'usage_count' => 1,
    ]);
    $service = new DiscountService;

    $result = $service->incrementCouponUsage($coupon);

    $this->assertTrue($result);
    $coupon->refresh();
    $this->assertEquals(2, $coupon->usage_count);

    $result = $service->incrementCouponUsage($coupon);
    $this->assertFalse($result);
    $coupon->refresh();
    $this->assertEquals(2, $coupon->usage_count);
});

it('excludes future-dated discounts from bestDiscountForOrder', function () {
    $discount = Discount::factory()->percentage()->create([
        'value' => 50,
        'starts_at' => now()->addWeek(),
    ]);

    $service = new DiscountService;
    $result = $service->bestDiscountForOrder(1000);

    $this->assertNull($result);
});

it('excludes expired discounts from bestDiscountForOrder', function () {
    $discount = Discount::factory()->percentage()->create([
        'value' => 50,
        'ends_at' => now()->subWeek(),
    ]);

    $service = new DiscountService;
    $result = $service->bestDiscountForOrder(1000);

    $this->assertNull($result);
});

it('discount with no targets applies to all products', function () {
    $product = Product::factory()->create(['price' => 1000]);
    $discount = Discount::factory()->percentage()->create(['value' => 20]);
    // No targets attached — should match all products

    $service = new DiscountService;
    $result = $service->bestDiscountForProduct($product, 1000);

    $this->assertNotNull($result);
    $this->assertEquals($discount->id, $result['discount']->id);
    $this->assertEquals(200, $result['amount']);
});

it('discount with no targets applies via category match', function () {
    $product = Product::factory()->create(['price' => 1000]);
    $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes']);
    $product->categories()->attach($category);

    $discount = Discount::factory()->percentage()->create(['value' => 15]);
    // No targets attached — should match all products including this one

    $service = new DiscountService;
    $result = $service->bestDiscountForProduct($product, 1000);

    $this->assertNotNull($result);
    $this->assertEquals($discount->id, $result['discount']->id);
});

it('bestDiscountForOrder stacks coupon with stackable automatic discount', function () {
    $product = Product::factory()->create(['price' => 1000]);

    $autoDiscount = Discount::factory()->percentage()->create(['value' => 10, 'stackable' => true]);
    $autoDiscount->products()->attach($product);

    $couponDiscount = Discount::factory()->fixed()->create(['value' => 50, 'stackable' => true]);
    $coupon = Coupon::factory()->for($couponDiscount)->create([
        'code' => 'STACK50',
        'is_active' => true,
    ]);

    $service = new DiscountService;
    $result = $service->bestDiscountForOrder(1000, 'STACK50', [$product->id]);

    $this->assertNotNull($result);
    $this->assertTrue($result['stacked']);
    $this->assertCount(2, $result['discounts']);
    $this->assertEquals(150, $result['total_amount']);
});

it('bestDiscountForOrder blocks stacking when coupon is not stackable', function () {
    $product = Product::factory()->create(['price' => 1000]);

    $autoDiscount = Discount::factory()->percentage()->create(['value' => 10, 'stackable' => true]);
    $autoDiscount->products()->attach($product);

    $couponDiscount = Discount::factory()->fixed()->create(['value' => 50, 'stackable' => false]);
    Coupon::factory()->for($couponDiscount)->create([
        'code' => 'NOSTACK',
        'is_active' => true,
    ]);

    $service = new DiscountService;
    $result = $service->bestDiscountForOrder(1000, 'NOSTACK', [$product->id]);

    $this->assertNotNull($result);
    $this->assertFalse($result['stacked'] ?? false);
    // Auto 10% of 1000 = 100 beats coupon 50, so auto applies alone.
    $this->assertEquals($autoDiscount->id, $result['discount']->id);
    $this->assertEquals(100, $result['amount']);
});

it('bestDiscountForOrder lets bigger coupon win when not stackable', function () {
    $product = Product::factory()->create(['price' => 1000]);

    $autoDiscount = Discount::factory()->percentage()->create(['value' => 10, 'stackable' => false]);
    $autoDiscount->products()->attach($product);

    $couponDiscount = Discount::factory()->fixed()->create(['value' => 200, 'stackable' => true]);
    Coupon::factory()->for($couponDiscount)->create([
        'code' => 'BIGSAVE',
        'is_active' => true,
    ]);

    $service = new DiscountService;
    $result = $service->bestDiscountForOrder(1000, 'BIGSAVE', [$product->id]);

    $this->assertNotNull($result);
    $this->assertFalse($result['stacked'] ?? false);
    $this->assertEquals($couponDiscount->id, $result['discount']->id);
    $this->assertEquals(200, $result['amount']);
});

it('bestDiscountForOrder filters by productIds', function () {
    $product1 = Product::factory()->create(['price' => 1000]);
    $product2 = Product::factory()->create(['price' => 500]);

    $discount = Discount::factory()->percentage()->create(['value' => 50]);
    $discount->products()->attach($product1);

    $service = new DiscountService;
    $result = $service->bestDiscountForOrder(1000, null, [$product2->id]);

    $this->assertNull($result);
});

it('increments usage only for the given discount', function () {
    $target = Discount::factory()->create([
        'usage_limit' => 5,
        'usage_count' => 1,
    ]);
    $other = Discount::factory()->create([
        'usage_limit' => 5,
        'usage_count' => 1,
    ]);
    $service = new DiscountService;

    $result = $service->incrementUsage($target);

    $this->assertTrue($result);
    expect($target->fresh()->usage_count)->toBe(2);
    expect($other->fresh()->usage_count)->toBe(1);
});

it('increments coupon usage only for the given coupon', function () {
    $discount = Discount::factory()->create(['is_active' => true]);
    $target = Coupon::factory()->for($discount)->create([
        'usage_limit' => 5,
        'usage_count' => 1,
    ]);
    $other = Coupon::factory()->for($discount)->create([
        'usage_limit' => 5,
        'usage_count' => 1,
    ]);
    $service = new DiscountService;

    $result = $service->incrementCouponUsage($target);

    $this->assertTrue($result);
    expect($target->fresh()->usage_count)->toBe(2);
    expect($other->fresh()->usage_count)->toBe(1);
});
