<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'payment.enabled.bkash' => true,
        'payment.enabled.sslcommerz' => true,
        'payment.enabled.cod' => true,
    ]);
});

it('can checkout with cod', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    $cart = createCartWithItem($user, $product, 2);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'order' => [
                'status' => 'confirmed',
                'total' => '1060.00',
            ],
            'message' => 'Order confirmed. Pay on delivery.',
        ],
    ]);

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'status' => 'confirmed',
        'total' => '1060.00',
    ]);

    $this->assertDatabaseHas('carts', [
        'id' => $cart->id,
        'status' => 'converted',
    ]);
});

it('preserves separate variant discounts from cart through checkout', function (string $mode) {
    config(['discounts.combination_mode' => $mode]);
    $user = createUser();
    $product = Product::factory()->create(['type' => 'variable', 'is_active' => true]);
    $firstVariant = ProductVariant::factory()->for($product)->create(['price' => 100]);
    $secondVariant = ProductVariant::factory()->for($product)->create(['price' => 200]);
    Inventory::factory()->forVariant($firstVariant)->withQuantity(10)->create();
    Inventory::factory()->forVariant($secondVariant)->withQuantity(10)->create();
    $discount = Discount::factory()->percentage()->create(['value' => 10, 'coupon_only' => false]);
    $discount->products()->attach($product);
    $service = app(CartService::class);
    $cart = $service->getOrCreateForUser($user);
    $service->addItem($cart, $product, $firstVariant, 1);
    $service->addItem($cart, $product, $secondVariant, 1);
    $summary = $service->getCartSummary($cart);

    expect($summary['items'][0]['item_discounts'][0]['amount'])->toBe(10.0);
    expect($summary['items'][1]['item_discounts'][0]['amount'])->toBe(20.0);
    expect($summary['discount_total'])->toBe(30.0);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertOk();
    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'subtotal' => 300,
        'discount_total' => 30,
        'total' => 330,
    ]);
})->with(['single_winner', 'waterfall', 'best_per_line']);

it('can checkout with bkash', function () {
    config([
        'payment.gateways.bkash.app_key' => 'test_key',
        'payment.gateways.bkash.app_secret' => 'test_secret',
        'payment.gateways.bkash.username' => 'test_user',
        'payment.gateways.bkash.password' => 'test_pass',
        'payment.gateways.bkash.sandbox' => true,
    ]);

    Http::fake([
        'tokenized.sandbox.bka.sh/*token/grant' => Http::response([
            'id_token' => 'fake_token_123',
            'expires_in' => 3600,
        ], 200),
        'tokenized.sandbox.bka.sh/*checkout/create' => Http::response([
            'statusCode' => '0000',
            'paymentID' => 'BKASH_PAY_TEST',
            'bkashURL' => 'https://sandbox.bka.sh/pay?token=abc',
        ], 200),
    ]);

    $user = createUser();
    $product = createProduct(500, 20);
    $cart = createCartWithItem($user, $product, 1);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'bkash',
    ], authHeaders($user));

    $response->assertOk()->assertJsonStructure([
        'success',
        'data' => [
            'order' => ['id', 'order_number', 'status', 'total'],
            'payment' => ['id', 'redirect_url'],
        ],
    ]);

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('payments', [
        'method' => 'bkash',
        'status' => 'pending',
    ]);
});

it('returns 502 when gateway initiation fails', function () {
    config([
        'payment.gateways.bkash.app_key' => 'test_key',
        'payment.gateways.bkash.app_secret' => 'test_secret',
        'payment.gateways.bkash.username' => 'test_user',
        'payment.gateways.bkash.password' => 'test_pass',
        'payment.gateways.bkash.sandbox' => true,
    ]);

    Http::fake([
        'tokenized.sandbox.bka.sh/*token/grant' => Http::response([
            'id_token' => 'fake_token_123',
            'expires_in' => 3600,
        ], 200),
        'tokenized.sandbox.bka.sh/*checkout/create' => Http::response([
            'statusCode' => '1001',
            'statusMessage' => 'Invalid credentials',
        ], 200),
    ]);

    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'bkash',
    ], authHeaders($user));

    $response->assertStatus(502)->assertJson([
        'success' => false,
    ]);
});

it('cannot checkout with empty cart', function () {
    $user = createUser();

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Cart is empty.',
    ]);
});

it('cannot checkout with insufficient stock', function () {
    $user = createUser();
    $product = createProduct(500, 5);
    $cart = createCartWithItem($user, $product, 10);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertStatus(422)->assertJson([
        'success' => false,
    ]);
});

it('allows guest checkout with guest_email', function () {
    $product = createProduct(500, 20);

    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-test-token']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
        'guest_email' => 'guest@example.com',
    ], ['X-Guest-Token' => 'guest-test-token']);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'order' => [
                'status' => 'confirmed',
                'total' => '560.00',
            ],
        ],
    ]);

    $this->assertDatabaseHas('orders', [
        'user_id' => null,
        'guest_email' => 'guest@example.com',
        'status' => 'confirmed',
        'total' => '560.00',
    ]);
});

it('requires guest_email for unauthenticated checkout', function () {
    $product = createProduct(500, 20);

    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-test-token-2']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], ['X-Guest-Token' => 'guest-test-token-2']);

    $response->assertStatus(422)->assertJsonValidationErrors(['guest_email']);
});

it('guest order has null user_id and stores guest_email', function () {
    $product = createProduct(500, 20);

    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-test-token-3']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
        'guest_email' => 'guest@example.com',
    ], ['X-Guest-Token' => 'guest-test-token-3']);

    $response->assertOk();

    $order = Order::where('guest_email', 'guest@example.com')->first();
    expect($order)->not->toBeNull();
    expect($order->user_id)->toBeNull();
    expect($order->guest_email)->toBe('guest@example.com');
    expect($order->total)->toBe('1060.00');
});

it('guest order stores phone as guest_phone', function () {
    $product = createProduct(500, 20);

    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-test-token-phone']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'phone' => '01912345678',
        'payment_method' => 'cod',
        'guest_email' => 'phone-test@example.com',
    ], ['X-Guest-Token' => 'guest-test-token-phone']);

    $order = Order::where('guest_email', 'phone-test@example.com')->first();
    expect($order)->not->toBeNull();
    expect($order->guest_phone)->toBe('01912345678');
    expect($order->shipping_phone)->toBe('01912345678');
});

it('stores delivery_phone separately when provided', function () {
    $product = createProduct(500, 20);

    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-test-token-delivery']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'phone' => '01912345678',
        'delivery_phone' => '01812345678',
        'payment_method' => 'cod',
        'guest_email' => 'delivery-test@example.com',
    ], ['X-Guest-Token' => 'guest-test-token-delivery']);

    $order = Order::where('guest_email', 'delivery-test@example.com')->first();
    expect($order)->not->toBeNull();
    expect($order->guest_phone)->toBe('01912345678');
    expect($order->shipping_phone)->toBe('01812345678');
});

it('normalizes bangladesh phone numbers', function () {
    $product = createProduct(500, 20);

    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-test-token-norm']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'phone' => '+8801712345678',
        'payment_method' => 'cod',
        'guest_email' => 'norm-test@example.com',
    ], ['X-Guest-Token' => 'guest-test-token-norm']);

    $order = Order::where('guest_email', 'norm-test@example.com')->first();
    expect($order)->not->toBeNull();
    expect($order->guest_phone)->toBe('01712345678');
});

it('guest can look up order by email and order number', function () {
    $product = createProduct(500, 20);

    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-test-token-4']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
        'guest_email' => 'lookup@example.com',
    ], ['X-Guest-Token' => 'guest-test-token-4']);

    $order = Order::where('guest_email', 'lookup@example.com')->first();

    $response = $this->postJson('/api/orders/lookup', [
        'email' => 'lookup@example.com',
        'order_number' => $order->order_number,
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'order_number' => $order->order_number,
        ],
    ]);
});

it('guest can look up order by phone and order number', function () {
    $product = createProduct(500, 20);

    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-test-token-lookup-phone']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'phone' => '01798765432',
        'payment_method' => 'cod',
        'guest_email' => 'lookup-phone@example.com',
    ], ['X-Guest-Token' => 'guest-test-token-lookup-phone']);

    $order = Order::where('guest_email', 'lookup-phone@example.com')->first();

    $response = $this->postJson('/api/orders/lookup', [
        'phone' => '01798765432',
        'order_number' => $order->order_number,
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'order_number' => $order->order_number,
        ],
    ]);
});

it('returns generic error for invalid order lookup', function () {
    $response = $this->postJson('/api/orders/lookup', [
        'email' => 'nobody@example.com',
        'order_number' => 'ORD-00000000-FAKE',
    ]);

    $response->assertStatus(404)->assertJson([
        'success' => false,
        'message' => 'We couldn\'t find an order matching those details.',
    ]);
});

it('returns error when neither email nor phone provided for lookup', function () {
    $response = $this->postJson('/api/orders/lookup', [
        'order_number' => 'ORD-00000000-FAKE',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('creates order items correctly', function () {
    $user = createUser();
    $product = createProduct(750, 30);
    $cart = createCartWithItem($user, $product, 3);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertOk();

    $orderId = $response->json('data.order.id');

    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_id' => $product->id,
        'unit_price' => 750.0,
        'quantity' => 3,
        'subtotal' => 2250.0,
        'total' => 2250.0,
    ]);
});

it('generates order number starting with ORD', function () {
    $user = createUser();
    $product = createProduct(100, 10);
    createCartWithItem($user, $product, 1);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertOk();

    $orderNumber = $response->json('data.order.order_number');

    expect($orderNumber)->toStartWith('ORD-');
});

it('sets expires_at for online payment', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'bkash',
    ], authHeaders($user));

    $order = Order::where('user_id', $user->id)->first();
    expect($order->expires_at)->not->toBeNull();
    expect($order->expires_at->isFuture())->toBeTrue();
});

it('has null expires_at for cod', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertOk();

    $order = Order::where('user_id', $user->id)->first();
    expect($order->expires_at)->toBeNull();
});

it('reserves inventory for online payment checkout', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 3);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'bkash',
    ], authHeaders($user));

    $inventory = Inventory::where('product_id', $product->id)->first();
    expect($inventory->quantity)->toBe(20);
    expect($inventory->reserved_quantity)->toBe(3);
});

it('does not deduct stock for pending online order', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 3);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'bkash',
    ], authHeaders($user));

    $inventory = Inventory::where('product_id', $product->id)->first();
    expect($inventory->quantity)->toBe(20);
});

it('deducts stock for cod order', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 3);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $inventory = Inventory::where('product_id', $product->id)->first();
    expect($inventory->quantity)->toBe(17);
    expect($inventory->reserved_quantity)->toBe(0);
});

it('allows checkout of exact remaining stock without double-reserving', function () {

    $user = createUser();
    $product = createProduct(500, 3);

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 3,
    ], authHeaders($user))->assertOk();

    expect(Inventory::where('product_id', $product->id)->first()->reserved_quantity)->toBe(3);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'order' => ['status' => 'confirmed'],
        ],
    ]);

    $inventory = Inventory::where('product_id', $product->id)->first();
    expect($inventory->quantity)->toBe(0);
    expect($inventory->reserved_quantity)->toBe(0);
});

it('rejects invalid payment method', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'bitcoin',
    ], authHeaders($user));

    $response->assertStatus(422);
});

it('requires shipping fields', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $response = $this->postJson('/api/checkout', [
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertStatus(422);
});

it('requires phone for guest checkout', function () {
    $product = createProduct(500, 20);

    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-test-token-phone-req']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    $rate = createTestShippingRate();

    $response = $this->postJson('/api/checkout', [
        'shipping_name' => 'Test User',
        'shipping_address' => '123 Test Street',
        'shipping_city' => 'Dhaka',
        'shipping_state' => 'Dhaka',
        'shipping_country' => 'Bangladesh',
        'payment_method' => 'cod',
        'guest_email' => 'nophone@example.com',
        'shipping_rate_id' => $rate->id,
    ], ['X-Guest-Token' => 'guest-test-token-phone-req']);

    $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
});

it('defaults phone to user phone for logged-in user', function () {
    $user = User::factory()->create(['phone' => '01999888777']);
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $rate = createTestShippingRate();

    $response = $this->postJson('/api/checkout', [
        'shipping_name' => 'Test User',
        'shipping_address' => '123 Test Street',
        'shipping_city' => 'Dhaka',
        'shipping_state' => 'Dhaka',
        'shipping_country' => 'Bangladesh',
        'payment_method' => 'cod',
        'shipping_rate_id' => $rate->id,
    ], authHeaders($user));

    $response->assertOk();

    $order = Order::where('user_id', $user->id)->first();
    expect($order)->not->toBeNull();
    expect($order->shipping_phone)->toBe('01999888777');
});

it('does not burn redemption when coupon gives no discount', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    $discount = Discount::factory()->fixed()->create([
        'value' => 50,
        'minimum_order_amount' => 5000,
        'is_active' => true,
    ]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'TOOHIGH',
        'is_active' => true,
        'per_user_limit' => 1,
    ]);

    foreach ([1, 2] as $i) {
        $cart = createCartWithItem($user, $product, 1);
        $cart->update(['coupon_id' => $coupon->id]);

        $this->postJson('/api/checkout', [
            ...shippingData(),
            'payment_method' => 'cod',
        ], authHeaders($user))->assertOk();
    }

    expect(CouponRedemption::where('coupon_id', $coupon->id)->count())->toBe(0);
});

it('blocks a second order reusing a single-use coupon', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    $discount = Discount::factory()->fixed()->create(['value' => 50, 'is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'ONCEONLY',
        'is_active' => true,
        'per_user_limit' => 1,
    ]);

    $cart = createCartWithItem($user, $product, 1);
    $cart->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user))->assertOk();

    $cart2 = createCartWithItem($user, $product, 1);
    $cart2->update(['coupon_id' => $coupon->id]);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'This coupon has already been used the maximum number of times.',
    ]);
});

it('allows a different user to reuse the same coupon', function () {
    $userA = createUser();
    $userB = createUser();
    $product = createProduct(500, 20);
    $discount = Discount::factory()->fixed()->create(['value' => 50, 'is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'SHARED10',
        'is_active' => true,
        'per_user_limit' => 1,
    ]);

    $cartA = createCartWithItem($userA, $product, 1);
    $cartA->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($userA))->assertOk();

    $cartB = createCartWithItem($userB, $product, 1);
    $cartB->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($userB))->assertOk();
});

it('respects per_user_limit greater than one', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    $discount = Discount::factory()->fixed()->create(['value' => 50, 'is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'TWICEONLY',
        'is_active' => true,
        'per_user_limit' => 2,
    ]);

    foreach ([1, 2] as $i) {
        $cart = createCartWithItem($user, $product, 1);
        $cart->update(['coupon_id' => $coupon->id]);

        $this->postJson('/api/checkout', [
            ...shippingData(),
            'payment_method' => 'cod',
        ], authHeaders($user))->assertOk();
    }

    $cart3 = createCartWithItem($user, $product, 1);
    $cart3->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user))->assertStatus(422);
});

it('releases coupon redemption when order is cancelled', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    $discount = Discount::factory()->fixed()->create(['value' => 50, 'is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'REUSABLE',
        'is_active' => true,
        'per_user_limit' => 1,
    ]);

    $cart = createCartWithItem($user, $product, 1);
    $cart->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user))->assertOk();

    $order = Order::where('user_id', $user->id)->first();

    $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel")->assertOk();

    $cart2 = createCartWithItem($user, $product, 1);
    $cart2->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user))->assertOk();
});

it('keys guest coupon redemptions by email', function () {
    $product = createProduct(500, 20);
    $discount = Discount::factory()->fixed()->create(['value' => 50, 'is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'GUESTONCE',
        'is_active' => true,
        'per_user_limit' => 1,
    ]);

    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-coupon-1']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
    $cart->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
        'guest_email' => 'guestonce@example.com',
    ], ['X-Guest-Token' => 'guest-coupon-1'])->assertOk();

    $cart2 = Cart::create(['status' => 'active', 'guest_token' => 'guest-coupon-2']);
    CartItem::create(['cart_id' => $cart2->id, 'product_id' => $product->id, 'quantity' => 1]);
    $cart2->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
        'guest_email' => 'guestonce@example.com',
    ], ['X-Guest-Token' => 'guest-coupon-2'])->assertStatus(422);
});

it('does not increment global usage when coupon gives no discount', function () {
    $user = createUser();
    $product = createProduct(500, 20);
    $discount = Discount::factory()->fixed()->create([
        'value' => 50,
        'minimum_order_amount' => 5000,
        'is_active' => true,
    ]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'NOBENEFIT',
        'is_active' => true,
        'per_user_limit' => 1,
    ]);

    $cart = createCartWithItem($user, $product, 1);
    $cart->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user))->assertOk();

    expect($coupon->fresh()->usage_count)->toBe(0);
    expect($discount->fresh()->usage_count)->toBe(0);
});

it('returns a generic message when gateway initiation fails', function () {
    config(['payment.enabled.stripe' => true]);
    config([
        'payment.gateways.stripe.secret_key' => 'sk_test_stripe',
        'payment.gateways.stripe.base_url' => 'https://api.stripe.com',
    ]);
    Http::fake([
        'api.stripe.com/*' => Http::response(['error' => ['message' => 'secret-internal-failure', 'leak' => 'should-not-appear']], 500),
    ]);

    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 2);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'stripe',
    ], authHeaders($user));

    $response->assertStatus(502);
    expect($response->json('message'))
        ->not->toContain('secret-internal-failure')
        ->not->toContain('should-not-appear');
});
