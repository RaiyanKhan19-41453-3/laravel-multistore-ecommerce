<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

it('returns empty cart for unauthenticated guest', function () {
    $response = $this->getJson('/api/cart', [
        'X-Guest-Token' => 'test-uuid-token',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'item_count' => 0,
            'subtotal' => 0,
            'total' => 0,
        ],
    ]);
});

it('guest can add simple product to cart', function () {
    $product = Product::factory()->create(['price' => 500, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $response = $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ], [
        'X-Guest-Token' => 'test-uuid-1',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'item_count' => 2,
            'subtotal' => 1000,
        ],
    ]);

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $inventory = Inventory::where('product_id', $product->id)->first();
    $this->assertEquals(2, $inventory->reserved_quantity);
});

it('auth user can add product to cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 300, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(20)->create();

    $response = $this->actingAs($user)
        ->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'item_count' => 1,
            'subtotal' => 300,
        ],
    ]);

    $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
});

it('duplicate item increments quantity', function () {
    $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ], ['X-Guest-Token' => 'test-uuid-2']);

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 3,
    ], ['X-Guest-Token' => 'test-uuid-2']);

    $cart = Cart::where('guest_token', 'test-uuid-2')->first();
    $this->assertEquals(1, $cart->items()->count());
    $this->assertEquals(5, $cart->items()->first()->quantity);

    $inventory = Inventory::where('product_id', $product->id)->first();
    $this->assertEquals(5, $inventory->reserved_quantity);
});

it('rejects item when quantity exceeds stock', function () {
    $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(5)->create();

    $response = $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 10,
    ], ['X-Guest-Token' => 'test-uuid-3']);

    $response->assertStatus(422)->assertJson([
        'success' => false,
    ]);

    $this->assertDatabaseEmpty('cart_items');
});

it('rejects inactive product', function () {
    $product = Product::factory()->create(['is_active' => false]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $response = $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ], ['X-Guest-Token' => 'test-uuid-4']);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Product is not available.',
    ]);
});

it('can update cart item quantity', function () {
    $product = Product::factory()->create(['price' => 200, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ], ['X-Guest-Token' => 'test-uuid-5']);

    $cart = Cart::where('guest_token', 'test-uuid-5')->first();
    $item = $cart->items()->first();

    $response = $this->patchJson("/api/cart/items/{$item->id}", [
        'quantity' => 5,
    ], ['X-Guest-Token' => 'test-uuid-5']);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'item_count' => 5,
            'subtotal' => 1000,
        ],
    ]);

    $inventory = Inventory::where('product_id', $product->id)->first();
    $this->assertEquals(5, $inventory->reserved_quantity);
});

it('can remove cart item and release stock', function () {
    $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 3,
    ], ['X-Guest-Token' => 'test-uuid-6']);

    $cart = Cart::where('guest_token', 'test-uuid-6')->first();
    $item = $cart->items()->first();

    $response = $this->deleteJson("/api/cart/items/{$item->id}", [], [
        'X-Guest-Token' => 'test-uuid-6',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'item_count' => 0,
            'subtotal' => 0,
        ],
    ]);

    $inventory = Inventory::where('product_id', $product->id)->first();
    $this->assertEquals(0, $inventory->reserved_quantity);
});

it('can clear entire cart', function () {
    $product1 = Product::factory()->create(['price' => 100, 'is_active' => true]);
    $product2 = Product::factory()->create(['price' => 200, 'is_active' => true]);
    Inventory::factory()->forProduct($product1)->withQuantity(50)->create();
    Inventory::factory()->forProduct($product2)->withQuantity(50)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $product1->id,
        'quantity' => 2,
    ], ['X-Guest-Token' => 'test-uuid-7']);

    $this->postJson('/api/cart/items', [
        'product_id' => $product2->id,
        'quantity' => 1,
    ], ['X-Guest-Token' => 'test-uuid-7']);

    $response = $this->deleteJson('/api/cart', [], [
        'X-Guest-Token' => 'test-uuid-7',
    ]);

    $response->assertOk();

    $inventory1 = Inventory::where('product_id', $product1->id)->first();
    $inventory2 = Inventory::where('product_id', $product2->id)->first();
    $this->assertEquals(0, $inventory1->reserved_quantity);
    $this->assertEquals(0, $inventory2->reserved_quantity);
});

it('cart summary returns live prices not stored prices', function () {
    $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ], ['X-Guest-Token' => 'test-uuid-8']);

    $product->update(['price' => 250]);

    $response = $this->getJson('/api/cart', [
        'X-Guest-Token' => 'test-uuid-8',
    ]);

    $response->assertOk()->assertJson([
        'data' => [
            'subtotal' => 500,
        ],
    ]);
});

it('can apply valid coupon', function () {
    $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $discount = Discount::factory()->percentage()->create(['value' => 10, 'is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create(['code' => 'SAVE10', 'is_active' => true]);

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ], ['X-Guest-Token' => 'test-uuid-9']);

    $response = $this->postJson('/api/cart/coupon', [
        'code' => 'SAVE10',
    ], ['X-Guest-Token' => 'test-uuid-9']);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'coupon_code' => 'SAVE10',
            'discount_total' => 100,
            'total' => 900,
        ],
    ]);
});

it('rejects invalid coupon', function () {
    $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ], ['X-Guest-Token' => 'test-uuid-10']);

    $response = $this->postJson('/api/cart/coupon', [
        'code' => 'INVALID',
    ], ['X-Guest-Token' => 'test-uuid-10']);

    $response->assertStatus(422)->assertJson([
        'success' => false,
    ]);
});

it('can remove coupon from cart', function () {
    $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $discount = Discount::factory()->percentage()->create(['value' => 10, 'is_active' => true]);
    Coupon::factory()->for($discount)->create(['code' => 'SAVE10', 'is_active' => true]);

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ], ['X-Guest-Token' => 'test-uuid-11']);

    $this->postJson('/api/cart/coupon', [
        'code' => 'SAVE10',
    ], ['X-Guest-Token' => 'test-uuid-11']);

    $response = $this->deleteJson('/api/cart/coupon', [], [
        'X-Guest-Token' => 'test-uuid-11',
    ]);

    $response->assertOk()->assertJson([
        'data' => [
            'coupon_code' => null,
        ],
    ]);

    $cart = Cart::where('guest_token', 'test-uuid-11')->first();
    $this->assertNull($cart->coupon_id);
});

it('guest cart merge on login', function () {
    $user = User::factory()->create();
    $product1 = Product::factory()->create(['price' => 100, 'is_active' => true]);
    $product2 = Product::factory()->create(['price' => 200, 'is_active' => true]);
    Inventory::factory()->forProduct($product1)->withQuantity(50)->create();
    Inventory::factory()->forProduct($product2)->withQuantity(50)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $product1->id,
        'quantity' => 2,
    ], ['X-Guest-Token' => 'guest-token-merge']);

    $this->postJson('/api/cart/items', [
        'product_id' => $product2->id,
        'quantity' => 1,
    ], ['X-Guest-Token' => 'guest-token-merge']);

    $response = $this->actingAs($user)
        ->postJson('/api/cart/merge', [
            'guest_token' => 'guest-token-merge',
        ]);

    $response->assertOk();

    $userCart = Cart::where('user_id', $user->id)->first();
    $this->assertNotNull($userCart);
    $this->assertEquals(2, $userCart->items()->count());

    $guestCart = Cart::where('guest_token', 'guest-token-merge')->first();
    $this->assertEquals('merged', $guestCart->status);
});

it('merge sums quantities for duplicate items', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ], ['X-Guest-Token' => 'guest-merge-dup']);

    $this->actingAs($user)
        ->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

    $this->actingAs($user)
        ->postJson('/api/cart/merge', [
            'guest_token' => 'guest-merge-dup',
        ]);

    $userCart = Cart::where('user_id', $user->id)->first();
    $this->assertEquals(1, $userCart->items()->count());
    $this->assertEquals(3, $userCart->items()->first()->quantity);
});

it('unauthenticated access returns 401', function () {
    $response = $this->postJson('/api/auth/logout');

    $response->assertStatus(401);
});

it('user can register and receive token', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '01712345678',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'user' => ['name' => 'Test User', 'email' => 'test@example.com', 'phone' => '01712345678'],
        ],
    ]);

    $response->assertJsonStructure(['data' => ['token']]);
});

it('user can login and receive token', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'identifier' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
    ]);

    $response->assertJsonStructure(['data' => ['user', 'token']]);
});

it('login rejects invalid credentials', function () {
    $response = $this->postJson('/api/auth/login', [
        'identifier' => 'nonexistent@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)->assertJson([
        'success' => false,
    ]);
});

it('cart item removed on product force delete', function () {
    $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ], ['X-Guest-Token' => 'test-cascade']);

    $this->assertDatabaseHas('cart_items', ['product_id' => $product->id]);

    $product->forceDelete();

    $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
});

it('rejects cart item when variant does not belong to product', function () {
    $productA = Product::factory()->create(['price' => 100, 'is_active' => true]);
    $productB = Product::factory()->create(['price' => 200, 'is_active' => true]);
    $variantOfB = ProductVariant::factory()->create(['product_id' => $productB->id, 'is_active' => true]);
    Inventory::factory()->forProduct($productA)->withQuantity(50)->create();
    Inventory::factory()->forVariant($variantOfB)->withQuantity(50)->create();

    $response = $this->postJson('/api/cart/items', [
        'product_id' => $productA->id,
        'product_variant_id' => $variantOfB->id,
        'quantity' => 1,
    ], ['X-Guest-Token' => 'test-variant-mismatch']);

    $response->assertStatus(422)->assertJson([
        'success' => false,
    ]);

    $this->assertDatabaseMissing('cart_items', ['product_id' => $productA->id]);
});

it('merge does not double-reserve inventory', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ], ['X-Guest-Token' => 'guest-merge-reserve']);

    $this->actingAs($user)
        ->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

    $this->actingAs($user)
        ->postJson('/api/cart/merge', [
            'guest_token' => 'guest-merge-reserve',
        ])->assertOk();

    $userCart = Cart::where('user_id', $user->id)->first();
    $this->assertEquals(3, $userCart->items()->first()->quantity);

    $inventory = Inventory::where('product_id', $product->id)->first();
    $this->assertEquals(3, $inventory->reserved_quantity);
});

it('rejects applying an exhausted coupon at cart time', function () {
    $user = User::factory()->create();
    $discount = Discount::factory()->fixed()->create(['value' => 50, 'is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'USEDUP',
        'is_active' => true,
        'per_user_limit' => 1,
    ]);

    CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'identifier' => 'user:'.$user->id,
        'order_id' => null,
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/cart/coupon', [
            'code' => 'USEDUP',
        ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'This coupon has already been used the maximum number of times.',
    ]);
});

it('hides exhausted coupon discount in cart summary', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(50)->create();

    $discount = Discount::factory()->percentage()->create(['value' => 10, 'is_active' => true, 'coupon_only' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'SPENT10',
        'is_active' => true,
        'per_user_limit' => 1,
    ]);

    CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'identifier' => 'user:'.$user->id,
        'order_id' => null,
    ]);

    $cart = Cart::create(['user_id' => $user->id, 'status' => 'active', 'coupon_id' => $coupon->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    $response = $this->actingAs($user)->getJson('/api/cart');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'discount_total' => 0,
            'total' => 1000,
        ],
    ]);
});

it('treats coupon as invalid when its discount is deleted', function () {
    $user = User::factory()->create();
    $discount = Discount::factory()->fixed()->create(['value' => 50, 'is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'DEADDISC',
        'is_active' => true,
    ]);
    $discount->delete();

    $response = $this->actingAs($user)
        ->postJson('/api/cart/coupon', [
            'code' => 'DEADDISC',
        ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Invalid or inactive coupon code.',
    ]);
});
