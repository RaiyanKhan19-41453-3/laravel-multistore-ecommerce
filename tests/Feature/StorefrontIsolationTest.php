<?php

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Services\DiscountService;
use App\Support\CurrentStore;
use Illuminate\Database\QueryException;

it('allows the same product slug and sku in different stores', function () {
    $storeA = Store::factory()->create(['slug' => 'iso-a']);
    $storeB = Store::factory()->create(['slug' => 'iso-b']);

    Product::factory()->create(['slug' => 'same-slug', 'sku' => 'SAME-1', 'store_id' => $storeA->id]);
    Product::factory()->create(['slug' => 'same-slug', 'sku' => 'SAME-1', 'store_id' => $storeB->id]);

    // Same store still rejects duplicates at the database level.
    expect(fn () => Product::factory()->create(['slug' => 'same-slug', 'sku' => 'OTHER', 'store_id' => $storeA->id]))
        ->toThrow(QueryException::class);

    expect(Product::where('slug', 'same-slug')->count())->toBe(2);
});

it('serves each store its own product for a shared slug', function () {
    $storeA = Store::factory()->create(['slug' => 'shop-a']);
    $storeB = Store::factory()->create(['slug' => 'shop-b']);

    Product::factory()->create(['slug' => 'shared-tee', 'name' => 'Alpha Tee', 'is_active' => true, 'store_id' => $storeA->id]);
    Product::factory()->create(['slug' => 'shared-tee', 'name' => 'Beta Tee', 'is_active' => true, 'store_id' => $storeB->id]);

    $this->getJson('/api/products/shared-tee', ['X-Store-Slug' => 'shop-a'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Alpha Tee');

    $this->getJson('/api/products/shared-tee', ['X-Store-Slug' => 'shop-b'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Beta Tee');

    // The default store carries neither product, so it 404s instead of leaking.
    $this->getJson('/api/products/shared-tee')->assertNotFound();
});

it('scopes category and brand listings to the current store', function () {
    $storeA = Store::factory()->create(['slug' => 'cat-a']);
    $storeB = Store::factory()->create(['slug' => 'cat-b']);

    // No CategoryFactory exists yet; auto-fill assigns the current store.
    app(CurrentStore::class)->set($storeA);
    $categoryA = Category::create(['name' => 'Shoes', 'slug' => 'shoes', 'is_active' => true]);

    app(CurrentStore::class)->set($storeB);
    Category::create(['name' => 'Shoes', 'slug' => 'shoes', 'is_active' => true]);

    app(CurrentStore::class)->forget();

    $productA = Product::factory()->create(['is_active' => true, 'store_id' => $storeA->id]);
    $productA->categories()->attach($categoryA);
    $productB = Product::factory()->create(['is_active' => true, 'store_id' => $storeB->id]);

    $response = $this->getJson('/api/categories/shoes', ['X-Store-Slug' => 'cat-a'])->assertOk();

    $ids = collect($response->json('data.products.data'))->pluck('id');
    expect($ids)->toContain($productA->id)->and($ids)->not->toContain($productB->id);
});

it('validates admin slugs per store', function () {
    $admin = createAdmin();
    $storeA = Store::factory()->create(['slug' => 'adm-a']);

    Product::factory()->create(['slug' => 'dup-slug', 'sku' => 'DUP-SKU-1', 'store_id' => $storeA->id]);

    // Same store: blocked.
    $this->actingAs($admin)->post('/admin/products', [
        'name' => 'Dup Product',
        'slug' => 'dup-slug',
        'type' => 'simple',
        'sku' => 'DUP-SKU-1',
        'price' => 100,
    ], ['X-Store-Slug' => 'adm-a'])->assertSessionHasErrors(['slug', 'sku']);

    // Another store: allowed.
    $this->actingAs($admin)->post('/admin/products', [
        'name' => 'Dup Product',
        'slug' => 'dup-slug',
        'type' => 'simple',
        'sku' => 'DUP-SKU-1',
        'price' => 100,
    ])->assertSessionHasNoErrors();

    expect(Product::where('slug', 'dup-slug')->count())->toBe(2);
});

it('resolves the same coupon code to each store coupon', function () {
    $storeA = Store::factory()->create(['slug' => 'cpn-a']);
    $storeB = Store::factory()->create(['slug' => 'cpn-b']);

    $discountA = Discount::factory()->create(['store_id' => $storeA->id]);
    $discountB = Discount::factory()->create(['store_id' => $storeB->id]);

    $couponA = Coupon::factory()->create(['discount_id' => $discountA->id, 'store_id' => $storeA->id, 'code' => 'SAVE10']);
    $couponB = Coupon::factory()->create(['discount_id' => $discountB->id, 'store_id' => $storeB->id, 'code' => 'SAVE10']);

    app(CurrentStore::class)->set($storeA);
    expect(app(DiscountService::class)->applyCoupon('SAVE10')?->id)->toBe($couponA->id);

    app(CurrentStore::class)->set($storeB);
    expect(app(DiscountService::class)->applyCoupon('SAVE10')?->id)->toBe($couponB->id);

    app(CurrentStore::class)->forget();
});

it('isolates guest order lookup per store', function () {
    $storeA = Store::factory()->create(['slug' => 'ord-a']);
    $storeB = Store::factory()->create(['slug' => 'ord-b']);

    $orderA = Order::factory()->create(['order_number' => 'ORD-DUP-1', 'guest_email' => 'same@example.com', 'user_id' => null, 'store_id' => $storeA->id]);
    $orderB = Order::factory()->create(['order_number' => 'ORD-DUP-1', 'guest_email' => 'same@example.com', 'user_id' => null, 'store_id' => $storeB->id]);

    $this->postJson('/api/orders/lookup', [
        'email' => 'same@example.com',
        'order_number' => 'ORD-DUP-1',
    ], ['X-Store-Slug' => 'ord-a'])->assertOk()->assertJsonPath('data.id', $orderA->id);

    $this->postJson('/api/orders/lookup', [
        'email' => 'same@example.com',
        'order_number' => 'ORD-DUP-1',
    ], ['X-Store-Slug' => 'ord-b'])->assertOk()->assertJsonPath('data.id', $orderB->id);
});
