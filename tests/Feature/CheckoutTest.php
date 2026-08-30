<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function createUser(): User
{
    return User::factory()->create();
}

function authHeaders(User $user): array
{
    $token = $user->createToken('test-token')->plainTextToken;

    return ['Authorization' => "Bearer $token"];
}

function createProduct(float $price = 500, int $stock = 50): Product
{
    $product = Product::factory()->create(['price' => $price, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity($stock)->create();

    return $product;
}

function createCartWithItem(User $user, Product $product, int $quantity = 1): Cart
{
    $cart = Cart::create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
    ]);

    return $cart;
}

function createTestShippingRate(): ShippingRate
{
    $method = ShippingMethod::firstOrCreate(['name' => 'Standard'], ['is_active' => true, 'estimated_days' => 5]);
    $zone = ShippingZone::firstOrCreate(['name' => 'Dhaka'], [
        'cities' => ['Dhaka'],
        'is_fallback' => false,
        'is_active' => true,
    ]);

    return ShippingRate::firstOrCreate(
        ['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id],
        ['price' => 60, 'free_shipping_min' => null]
    );
}

function shippingData(): array
{
    $rate = createTestShippingRate();

    return [
        'shipping_name' => 'Test User',
        'phone' => '01712345678',
        'shipping_address' => '123 Test Street',
        'shipping_city' => 'Dhaka',
        'shipping_state' => 'Dhaka',
        'shipping_country' => 'Bangladesh',
        'shipping_rate_id' => $rate->id,
    ];
}

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

it('can checkout with bkash', function () {
    Http::fake([
        'sandbox.sslcommerz.com/*' => Http::response([
            'status' => 'SUCCESS',
            'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay?token=abc123',
            'sessionkey' => 'session123',
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
    Http::fake([
        'sandbox.sslcommerz.com/*' => Http::response([
            'status' => 'FAILED',
            'failedreason' => 'Invalid credentials',
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
