<?php

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Services\CartService;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\DB;

it('keeps a separate active cart per store for the same user', function () {
    $user = createUser();
    $storeA = Store::factory()->create(['slug' => 'cart-a']);
    $storeB = Store::factory()->create(['slug' => 'cart-b']);
    $service = app(CartService::class);

    $cartA = $service->getOrCreateForUser($user, $storeA->id);
    $cartB = $service->getOrCreateForUser($user, $storeB->id);

    expect($cartA->id)->not->toBe($cartB->id)
        ->and($cartA->store_id)->toBe($storeA->id)
        ->and($cartB->store_id)->toBe($storeB->id);

    // Re-resolving the same store returns the same cart.
    expect($service->getOrCreateForUser($user, $storeA->id)->id)->toBe($cartA->id);
});

it('keeps guest carts separate per store for the same token', function () {
    $storeA = Store::factory()->create(['slug' => 'gcart-a']);
    $storeB = Store::factory()->create(['slug' => 'gcart-b']);
    $service = app(CartService::class);

    $cartA = $service->getOrCreateForGuest('shared-token', $storeA->id);
    $cartB = $service->getOrCreateForGuest('shared-token', $storeB->id);

    expect($cartA->id)->not->toBe($cartB->id)
        ->and($cartA->store_id)->toBe($storeA->id)
        ->and($cartB->store_id)->toBe($storeB->id);
});

it('rejects items from another store and adopts a store for legacy carts', function () {
    $storeA = Store::factory()->create(['slug' => 'add-a']);
    $storeB = Store::factory()->create(['slug' => 'add-b']);
    $service = app(CartService::class);

    $productA = Product::factory()->create(['is_active' => true, 'store_id' => $storeA->id]);
    Inventory::factory()->forProduct($productA)->withQuantity(10)->create();
    $productB = Product::factory()->create(['is_active' => true, 'store_id' => $storeB->id]);
    Inventory::factory()->forProduct($productB)->withQuantity(10)->create();

    $cartA = $service->getOrCreateForGuest('tok-1', $storeA->id);

    $service->addItem($cartA, $productA, null, 1);

    expect(fn () => $service->addItem($cartA->fresh(), $productB, null, 1))
        ->toThrow(InvalidArgumentException::class);

    // A legacy cart without a store adopts its first item's store.
    $legacyId = DB::table('carts')->insertGetId([
        'guest_token' => 'legacy-tok',
        'status' => 'active',
        'store_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $legacy = Cart::find($legacyId);

    $service->addItem($legacy, $productA, null, 1);

    expect($legacy->fresh()->store_id)->toBe($storeA->id);
});

it('isolates carts across stores over the api', function () {
    $storeA = Store::factory()->create(['slug' => 'api-a']);
    $storeB = Store::factory()->create(['slug' => 'api-b']);

    $productA = Product::factory()->create(['is_active' => true, 'store_id' => $storeA->id]);
    Inventory::factory()->forProduct($productA)->withQuantity(10)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $productA->id,
        'quantity' => 2,
    ], ['X-Guest-Token' => 'shopper-1', 'X-Store-Slug' => 'api-a'])->assertOk();

    // Same guest token on store B sees an empty cart.
    $this->getJson('/api/cart', ['X-Guest-Token' => 'shopper-1', 'X-Store-Slug' => 'api-b'])
        ->assertOk()
        ->assertJsonPath('data.item_count', 0);

    // Store A still has its items.
    $this->getJson('/api/cart', ['X-Guest-Token' => 'shopper-1', 'X-Store-Slug' => 'api-a'])
        ->assertOk()
        ->assertJsonPath('data.item_count', 2);

    // Store B cannot add store A's product: the scoped lookup 404s.
    $this->postJson('/api/cart/items', [
        'product_id' => $productA->id,
        'quantity' => 1,
    ], ['X-Guest-Token' => 'shopper-1', 'X-Store-Slug' => 'api-b'])->assertNotFound();
});

it('does not merge a guest cart from another store', function () {
    $user = createUser();
    $storeA = Store::factory()->create(['slug' => 'mrg-a']);
    $storeB = Store::factory()->create(['slug' => 'mrg-b']);
    $service = app(CartService::class);

    $productA = Product::factory()->create(['is_active' => true, 'store_id' => $storeA->id]);
    Inventory::factory()->forProduct($productA)->withQuantity(10)->create();

    $guestCart = $service->getOrCreateForGuest('merge-tok', $storeA->id);
    $service->addItem($guestCart, $productA, null, 1);

    app(CurrentStore::class)->set($storeB);
    $service->mergeGuestCart($user, 'merge-tok');
    app(CurrentStore::class)->forget();

    // Guest cart untouched, user cart for B is empty.
    expect($guestCart->fresh()->status)->toBe('active')
        ->and($service->getOrCreateForUser($user, $storeB->id)->getItemCount())->toBe(0);
});

it('rejects coupons from another store', function () {
    $storeA = Store::factory()->create(['slug' => 'cpa-a']);
    $storeB = Store::factory()->create(['slug' => 'cpa-b']);

    $discountB = Discount::factory()->create(['store_id' => $storeB->id]);
    Coupon::factory()->create(['discount_id' => $discountB->id, 'store_id' => $storeB->id, 'code' => 'ONLYB']);

    $productA = Product::factory()->create(['is_active' => true, 'store_id' => $storeA->id]);
    Inventory::factory()->forProduct($productA)->withQuantity(10)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $productA->id,
        'quantity' => 1,
    ], ['X-Guest-Token' => 'cpn-shopper', 'X-Store-Slug' => 'cpa-a'])->assertOk();

    $this->postJson('/api/cart/coupon', [
        'code' => 'ONLYB',
    ], ['X-Guest-Token' => 'cpn-shopper', 'X-Store-Slug' => 'cpa-a'])->assertStatus(422);
});

it('rejects another store shipping rate at checkout', function () {
    config(['payment.enabled.cod' => true]);

    $storeA = Store::factory()->create(['slug' => 'rte-a']);
    $storeB = Store::factory()->create(['slug' => 'rte-b']);

    $productB = Product::factory()->create(['price' => 500, 'is_active' => true, 'store_id' => $storeB->id]);
    Inventory::factory()->forProduct($productB)->withQuantity(20)->create();

    $methodA = ShippingMethod::create(['name' => 'Std A', 'is_active' => true, 'estimated_days' => 3]);
    $methodA->store_id = $storeA->id;
    $methodA->save();

    $zoneA = ShippingZone::create(['name' => 'Dhaka A', 'country' => 'Bangladesh', 'cities' => ['Dhaka'], 'is_fallback' => false, 'is_active' => true]);
    $zoneA->store_id = $storeA->id;
    $zoneA->save();

    $rateA = ShippingRate::create(['shipping_method_id' => $methodA->id, 'shipping_zone_id' => $zoneA->id, 'price' => 60]);
    $rateA->store_id = $storeA->id;
    $rateA->save();

    $headers = ['X-Guest-Token' => 'rte-guest', 'X-Store-Slug' => 'rte-b'];

    $this->postJson('/api/cart/items', [
        'product_id' => $productB->id,
        'quantity' => 1,
    ], $headers)->assertOk();

    $this->postJson('/api/checkout', [
        'shipping_name' => 'Guest User',
        'phone' => '01712345678',
        'guest_email' => 'guest-rte@example.com',
        'shipping_address' => '123 Test Street',
        'shipping_city' => 'Dhaka',
        'shipping_country' => 'Bangladesh',
        'payment_method' => 'cod',
        'shipping_rate_id' => $rateA->id,
    ], $headers)->assertStatus(422);

    expect(Order::where('guest_email', 'guest-rte@example.com')->count())->toBe(0);
});

it('binds checkout orders to the cart store with the cart store shipping rate', function () {
    config(['payment.enabled.cod' => true]);

    $storeB = Store::factory()->create(['slug' => 'chk-b', 'country' => 'BD', 'currency' => 'BDT']);

    $productB = Product::factory()->create(['price' => 500, 'is_active' => true, 'store_id' => $storeB->id]);
    Inventory::factory()->forProduct($productB)->withQuantity(20)->create();

    $method = ShippingMethod::create(['name' => 'Std B', 'is_active' => true, 'estimated_days' => 3]);
    $method->store_id = $storeB->id;
    $method->save();

    $zone = ShippingZone::create(['name' => 'Dhaka B', 'country' => 'Bangladesh', 'cities' => ['Dhaka'], 'is_fallback' => false, 'is_active' => true]);
    $zone->store_id = $storeB->id;
    $zone->save();

    $rate = ShippingRate::create(['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id, 'price' => 60]);
    $rate->store_id = $storeB->id;
    $rate->save();

    $headers = ['X-Guest-Token' => 'chk-guest', 'X-Store-Slug' => 'chk-b'];

    $this->postJson('/api/cart/items', [
        'product_id' => $productB->id,
        'quantity' => 2,
    ], $headers)->assertOk();

    $response = $this->postJson('/api/checkout', [
        'shipping_name' => 'Guest User',
        'phone' => '01712345678',
        'guest_email' => 'guest-b@example.com',
        'shipping_address' => '123 Test Street',
        'shipping_city' => 'Dhaka',
        'shipping_country' => 'Bangladesh',
        'payment_method' => 'cod',
        'shipping_rate_id' => $rate->id,
    ], $headers);

    $response->assertOk()->assertJsonPath('success', true);

    $order = Order::where('guest_email', 'guest-b@example.com')->firstOrFail();

    expect($order->store_id)->toBe($storeB->id)
        ->and($order->shipping_method_id)->toBe($method->id);

    expect($order->items()->where('store_id', $storeB->id)->count())->toBe($order->items()->count())
        ->and($order->items()->count())->toBeGreaterThan(0);
});
