<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

function fakeSslcommerzSuccess(): void
{
    Http::fake([
        'sandbox.sslcommerz.com/gwprocess/*' => Http::response([
            'status' => 'SUCCESS',
            'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/abc',
            'sessionkey' => 'sess_abc',
        ], 200),
    ]);
}

function fakeSslcommerzFailure(): void
{
    Http::fake([
        'sandbox.sslcommerz.com/gwprocess/*' => Http::response([
            'status' => 'FAILED',
            'failedreason' => 'Gateway down for maintenance',
        ], 200),
    ]);
}

function pendingGuestOrder(): Order
{
    return Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'guest@example.com',
        'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
        'total' => 1000,
    ]);
}

function pendingOnlinePayment(Order $order, array $overrides = []): Payment
{
    return Payment::factory()->create([
        'order_id' => $order->id,
        'method' => 'sslcommerz',
        'gateway' => 'sslcommerz',
        'status' => 'pending',
        'amount' => 1000,
        ...$overrides,
    ]);
}

it('retries payment on a pending order and returns a redirect url', function () {
    config(['payment.enabled.sslcommerz' => true]);
    fakeSslcommerzSuccess();

    $order = pendingGuestOrder();
    pendingOnlinePayment($order);

    $response = $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-1']);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'order_number' => $order->order_number,
            'status' => 'pending',
            'payment' => [
                'redirect_url' => 'https://sandbox.sslcommerz.com/pay/abc',
                'replayed' => false,
            ],
        ],
    ]);

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'idempotency_key' => 'key-1',
        'redirect_url' => 'https://sandbox.sslcommerz.com/pay/abc',
    ]);
});

it('replays the stored response when the same idempotency key is sent twice', function () {
    config(['payment.enabled.sslcommerz' => true]);
    fakeSslcommerzSuccess();

    $order = pendingGuestOrder();
    pendingOnlinePayment($order);

    $first = $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-1']);
    $second = $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-1']);

    $first->assertOk();
    $second->assertOk()->assertJsonPath('data.payment.replayed', true);

    expect($second->json('data.payment.id'))->toBe($first->json('data.payment.id'));
    expect(Payment::where('order_id', $order->id)->where('idempotency_key', 'key-1')->count())->toBe(1);
    Http::assertSentCount(1);
});

it('opens a new attempt when a different idempotency key is used', function () {
    config(['payment.enabled.sslcommerz' => true]);
    fakeSslcommerzSuccess();

    $order = pendingGuestOrder();
    pendingOnlinePayment($order);

    $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-1'])->assertOk();

    $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-2'])->assertOk();

    expect(Payment::where('order_id', $order->id)->whereNotNull('idempotency_key')->count())->toBe(2);
});

it('returns 409 when an attempt with the same key is still in flight', function () {
    config(['payment.enabled.sslcommerz' => true]);
    fakeSslcommerzSuccess();

    $order = pendingGuestOrder();
    $original = pendingOnlinePayment($order);
    pendingOnlinePayment($order, ['idempotency_key' => 'key-1', 'redirect_url' => null]);

    $response = $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-1']);

    $response->assertStatus(409);
    Http::assertSentCount(0);
    expect($original->fresh()->status)->toBe('pending');
});

it('re-executes a failed attempt with the same key on the same row', function () {
    config(['payment.enabled.sslcommerz' => true]);
    fakeSslcommerzSuccess();

    $order = pendingGuestOrder();
    pendingOnlinePayment($order);
    $failed = pendingOnlinePayment($order, ['idempotency_key' => 'key-1', 'status' => 'failed']);

    $response = $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-1']);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'payment' => [
                'id' => $failed->id,
                'redirect_url' => 'https://sandbox.sslcommerz.com/pay/abc',
                'replayed' => false,
            ],
        ],
    ]);

    expect(Payment::where('order_id', $order->id)->where('idempotency_key', 'key-1')->count())->toBe(1);
});

it('re-executes a stale pending attempt instead of reporting it in flight', function () {
    config(['payment.enabled.sslcommerz' => true]);
    fakeSslcommerzSuccess();

    $order = pendingGuestOrder();
    pendingOnlinePayment($order);
    $stale = pendingOnlinePayment($order, ['idempotency_key' => 'key-1', 'redirect_url' => null]);
    Payment::whereKey($stale->id)->update(['created_at' => now()->subMinutes(10)]);

    $response = $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-1']);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'payment' => [
                'id' => $stale->id,
                'redirect_url' => 'https://sandbox.sslcommerz.com/pay/abc',
                'replayed' => false,
            ],
        ],
    ]);

    Http::assertSentCount(1);
});

it('refuses retry for expired orders', function () {
    $order = pendingGuestOrder();
    $order->update(['expires_at' => now()->subMinute()]);
    pendingOnlinePayment($order);

    $response = $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-1']);

    $response->assertStatus(422)->assertJsonPath('message', 'This order has expired. Please place a new order.');
});

it('refuses retry for non-pending orders', function () {
    $order = pendingGuestOrder();
    $order->update(['status' => 'confirmed']);
    pendingOnlinePayment($order);

    $response = $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-1']);

    $response->assertStatus(422);
});

it('refuses retry when the order has no online payment', function () {
    $order = pendingGuestOrder();
    Payment::factory()->create([
        'order_id' => $order->id,
        'method' => 'cod',
        'gateway' => 'cod',
        'status' => 'pending',
        'amount' => 1000,
    ]);

    $response = $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'key-1']);

    $response->assertStatus(422);
});

it('returns 404 when guest proof does not match', function () {
    $order = pendingGuestOrder();
    pendingOnlinePayment($order);

    $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'stranger@example.com',
    ], ['Idempotency-Key' => 'key-1'])->assertNotFound();

    $this->postJson("/api/orders/{$order->order_number}/retry-payment", [], ['Idempotency-Key' => 'key-1'])
        ->assertNotFound();
});

it('returns 403 when another user retries the order', function () {
    $owner = createUser();
    $intruder = createUser();
    $order = Order::factory()->create([
        'user_id' => $owner->id,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
        'total' => 1000,
    ]);
    pendingOnlinePayment($order);

    $this->postJson("/api/orders/{$order->order_number}/retry-payment", [], authHeaders($intruder))
        ->assertForbidden();
});

it('lets the owner retry with a bearer token', function () {
    config(['payment.enabled.sslcommerz' => true]);
    fakeSslcommerzSuccess();

    $owner = createUser();
    $order = Order::factory()->create([
        'user_id' => $owner->id,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
        'total' => 1000,
    ]);
    pendingOnlinePayment($order);

    $this->postJson("/api/orders/{$order->order_number}/retry-payment", [], authHeaders($owner))
        ->assertOk()
        ->assertJsonPath('data.payment.redirect_url', 'https://sandbox.sslcommerz.com/pay/abc');
});

it('rejects an overlong idempotency key', function () {
    $order = pendingGuestOrder();
    pendingOnlinePayment($order);

    $this->postJson("/api/orders/{$order->order_number}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => str_repeat('k', 65)])->assertStatus(422);
});

it('includes the order number when checkout payment initiation fails', function () {
    config(['payment.enabled.sslcommerz' => true]);
    fakeSslcommerzFailure();

    $product = createProduct(500, 20);
    $cart = Cart::create(['status' => 'active', 'guest_token' => 'guest-retry-token']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'sslcommerz',
        'guest_email' => 'guest@example.com',
    ], ['X-Guest-Token' => 'guest-retry-token']);

    $response->assertStatus(502);
    $orderNumber = $response->json('data.order.order_number');
    expect($orderNumber)->not->toBeNull();

    // The cart is converted (the reported bug's setup); the order survives
    // pending so retry-payment can take over.
    expect($cart->fresh()->status)->toBe('converted');
    expect(CartItem::where('cart_id', $cart->id)->count())->toBe(0);
    $this->assertDatabaseHas('orders', ['order_number' => $orderNumber, 'status' => 'pending']);

    $retry = $this->postJson("/api/orders/{$orderNumber}/retry-payment", [
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'retry-after-failure']);

    $retry->assertStatus(502);
    expect(Payment::where('order_id', Order::where('order_number', $orderNumber)->first()->id)->count())->toBe(2);
});
