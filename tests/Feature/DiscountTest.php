<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super-admin');
});

test('admin can view discounts page', function () {
    Discount::factory()->count(3)->create();

    $response = $this->actingAs($this->user)
        ->get(route('admin.discounts.index'));

    $response->assertOk();
});

test('admin can create percentage discount', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.store'), [
            'name' => 'Summer Sale',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('discounts', [
        'name' => 'Summer Sale',
        'type' => 'percentage',
        'value' => 20,
    ]);
});

test('admin can create fixed discount', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.store'), [
            'name' => 'Eid 500 Off',
            'type' => 'fixed',
            'value' => 500,
            'minimum_order_amount' => 3000,
            'priority' => 10,
            'stackable' => true,
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('discounts', [
        'name' => 'Eid 500 Off',
        'type' => 'fixed',
        'value' => 500,
        'minimum_order_amount' => 3000,
        'priority' => 10,
        'stackable' => true,
    ]);
});

test('admin can attach products to discount', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();

    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.store'), [
            'name' => 'Product Sale',
            'type' => 'percentage',
            'value' => 10,
            'product_ids' => [$product1->id, $product2->id],
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $discount = Discount::where('name', 'Product Sale')->first();
    $this->assertCount(2, $discount->products);
});

test('admin can attach categories and brands to discount', function () {
    $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes']);
    $brand = Brand::create(['name' => 'Nike', 'slug' => 'nike']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.store'), [
            'name' => 'Category Sale',
            'type' => 'fixed',
            'value' => 100,
            'category_ids' => [$category->id],
            'brand_ids' => [$brand->id],
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $discount = Discount::where('name', 'Category Sale')->first();
    $this->assertCount(1, $discount->categories);
    $this->assertCount(1, $discount->brands);
});

test('admin can update discount', function () {
    $discount = Discount::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($this->user)
        ->put(route('admin.discounts.update', $discount), [
            'name' => 'New Name',
            'type' => 'percentage',
            'value' => 25,
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('discounts', [
        'id' => $discount->id,
        'name' => 'New Name',
    ]);
});

test('admin can toggle discount', function () {
    $discount = Discount::factory()->create(['is_active' => true]);

    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.toggle', $discount));

    $response->assertRedirect();

    $discount->refresh();
    $this->assertFalse($discount->is_active);
});

test('admin can delete discount', function () {
    $discount = Discount::factory()->create();

    $response = $this->actingAs($this->user)
        ->delete(route('admin.discounts.destroy', $discount));

    $response->assertRedirect();

    $this->assertSoftDeleted('discounts', ['id' => $discount->id]);
});

test('admin can create coupon for discount', function () {
    $discount = Discount::factory()->create();

    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.coupons.store', $discount), [
            'code' => 'WELCOME500',
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('coupons', [
        'discount_id' => $discount->id,
        'code' => 'WELCOME500',
    ]);
});

test('coupon code is uppercased', function () {
    $discount = Discount::factory()->create();

    $this->actingAs($this->user)
        ->post(route('admin.discounts.coupons.store', $discount), [
            'code' => 'summer20',
            'is_active' => true,
        ]);

    $this->assertDatabaseHas('coupons', [
        'discount_id' => $discount->id,
        'code' => 'SUMMER20',
    ]);
});

test('admin can toggle coupon', function () {
    $discount = Discount::factory()->create();
    $coupon = Coupon::factory()->for($discount)->create(['is_active' => true]);

    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.coupons.toggle', [$discount, $coupon]));

    $response->assertRedirect();

    $coupon->refresh();
    $this->assertFalse($coupon->is_active);
});

test('admin can delete coupon', function () {
    $discount = Discount::factory()->create();
    $coupon = Coupon::factory()->for($discount)->create();

    $response = $this->actingAs($this->user)
        ->delete(route('admin.discounts.coupons.destroy', [$discount, $coupon]));

    $response->assertRedirect();

    $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
});

test('unauthenticated user cannot view discounts', function () {
    $response = $this->get(route('admin.discounts.index'));

    $response->assertRedirect('/admin/login');
});

test('non-admin user cannot view discounts', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('admin.discounts.index'));

    $response->assertForbidden();
});

test('percentage discount cannot exceed 100', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.store'), [
            'name' => 'Invalid Discount',
            'type' => 'percentage',
            'value' => 150,
            'is_active' => true,
        ]);

    $response->assertSessionHasErrors('value');
});

test('fixed discount has no upper value limit', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.store'), [
            'name' => 'Big Fixed Discount',
            'type' => 'fixed',
            'value' => 5000,
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('discounts', [
        'name' => 'Big Fixed Discount',
        'value' => 5000,
    ]);
});

test('admin can attach variants to discount', function () {
    $product = Product::factory()->create();
    $variant1 = ProductVariant::factory()->for($product)->create(['name' => 'Small']);
    $variant2 = ProductVariant::factory()->for($product)->create(['name' => 'Large']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.store'), [
            'name' => 'Variant Sale',
            'type' => 'percentage',
            'value' => 15,
            'variant_ids' => [$variant1->id, $variant2->id],
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $discount = Discount::where('name', 'Variant Sale')->first();
    $this->assertCount(2, $discount->productVariants);
});

test('admin can attach variants and products to discount', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product2)->create(['name' => 'XL']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.discounts.store'), [
            'name' => 'Mixed Target Sale',
            'type' => 'fixed',
            'value' => 200,
            'product_ids' => [$product1->id],
            'variant_ids' => [$variant->id],
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $discount = Discount::where('name', 'Mixed Target Sale')->first();
    $this->assertCount(1, $discount->products);
    $this->assertCount(1, $discount->productVariants);
});
