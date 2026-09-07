<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\PaymentGateways\BkashGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'payment.enabled.bkash' => true,
        'payment.enabled.sslcommerz' => true,
        'payment.enabled.cod' => true,
    ]);
});

function createBkashTestProduct(float $price = 500, int $stock = 50): Product
{
    $product = Product::factory()->create(['price' => $price, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity($stock)->create();

    return $product;
}

function createBkashTestCart(User $user, Product $product, int $quantity = 1): Cart
{
    $cart = Cart::create(['user_id' => $user->id, 'status' => 'active']);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
    ]);

    return $cart;
}

function createBkashTestShippingRate(): ShippingRate
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

function bkashAuthHeaders(User $user): array
{
    $token = $user->createToken('test-token')->plainTextToken;

    return ['Authorization' => "Bearer $token"];
}

function createBkashOrder(string $status = 'pending'): Order
{
    $order = Order::factory()->create([
        'status' => $status,
        'subtotal' => 1000,
        'total' => 1000,
        'shipping_name' => 'Test Customer',
        'shipping_phone' => '01712345678',
        'shipping_address' => '123 Test St',
        'shipping_city' => 'Dhaka',
    ]);
    OrderItem::factory()->count(2)->for($order)->create();

    return $order;
}

function createBkashPayment(Order $order, string $status = 'pending'): Payment
{
    return Payment::factory()->create([
        'order_id' => $order->id,
        'gateway' => 'bkash',
        'status' => $status,
        'amount' => $order->total,
        'gateway_transaction_id' => 'BKASH_PAY_'.uniqid(),
    ]);
}

function fakeBkashTokenGrant(): void
{
    Http::fake([
        'tokenized.sandbox.bka.sh/*token/grant' => Http::response([
            'id_token' => 'fake_id_token_abc123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => 'fake_refresh_token',
        ], 200),
    ]);
}

function fakeBkashCreatePayment(string $paymentID = 'BKASH_PAY_TEST123', string $bkashURL = 'https://sandbox.bka.sh/pay'): void
{
    Http::fake([
        'tokenized.sandbox.bka.sh/*checkout/create' => Http::response([
            'statusCode' => '0000',
            'statusMessage' => 'Successful',
            'paymentID' => $paymentID,
            'bkashURL' => $bkashURL,
            'merchantInvoiceNumber' => 'ORDER_TEST',
        ], 200),
    ]);
}

function fakeBkashExecute(string $status = 'Completed'): void
{
    Http::fake([
        'tokenized.sandbox.bka.sh/*execute/*' => Http::response([
            'statusCode' => '0000',
            'statusMessage' => 'Successful',
            'transactionStatus' => $status,
            'paymentID' => 'BKASH_PAY_TEST123',
            'amount' => '1000',
        ], 200),
    ]);
}

function fakeBkashQuery(string $status = 'Completed'): void
{
    Http::fake([
        'tokenized.sandbox.bka.sh/*query' => Http::response([
            'statusCode' => '0000',
            'statusMessage' => 'Successful',
            'transactionStatus' => $status,
            'paymentID' => 'BKASH_PAY_TEST123',
            'amount' => '1000',
        ], 200),
    ]);
}

function fakeBkashRefund(bool $success = true): void
{
    Http::fake([
        'tokenized.sandbox.bka.sh/*refund' => Http::response([
            'statusCode' => $success ? '0000' : '2001',
            'statusMessage' => $success ? 'Successful' : 'Failed',
        ], 200),
    ]);
}

// --- Token Grant ---

it('grants access token and caches it', function () {
    fakeBkashTokenGrant();
    $gateway = new BkashGateway;

    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('getAccessToken');
    $method->setAccessible(true);

    $token = $method->invoke($gateway);

    expect($token)->toBe('fake_id_token_abc123');
    expect(Cache::get('bkash_access_token'))->toBe('fake_id_token_abc123');
});

it('returns cached token on subsequent calls', function () {
    Cache::put('bkash_access_token', 'cached_token_999', 3600);

    $gateway = new BkashGateway;
    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('getAccessToken');
    $method->setAccessible(true);

    $token = $method->invoke($gateway);

    expect($token)->toBe('cached_token_999');

    Http::assertNothingSent();
});

it('throws when token grant fails', function () {
    Http::fake([
        'tokenized.sandbox.bka.sh/*token/grant' => Http::response([
            'errorMessage' => 'Invalid credentials',
        ], 401),
    ]);

    $gateway = new BkashGateway;
    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('getAccessToken');
    $method->setAccessible(true);

    $method->invoke($gateway);
})->throws(RuntimeException::class, 'bKash token grant failed');

// --- initiatePayment ---

it('initiates bKash payment and stores paymentID on Payment', function () {
    fakeBkashTokenGrant();
    fakeBkashCreatePayment('BKASH_UNIQUE_123');

    $order = createBkashOrder();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'gateway' => 'bkash',
        'status' => 'pending',
        'amount' => $order->total,
    ]);

    $gateway = new BkashGateway;
    $result = $gateway->initiatePayment($order, $payment);

    expect($result['redirect_url'])->not->toBeEmpty();
    expect($result['payment_id'])->toBe('BKASH_UNIQUE_123');

    $payment->refresh();
    expect($payment->gateway_transaction_id)->toBe('BKASH_UNIQUE_123');
    expect($payment->gateway_response)->not->toBeNull();
});

it('throws when bKash create payment fails', function () {
    fakeBkashTokenGrant();

    Http::fake([
        'tokenized.sandbox.bka.sh/*checkout/create' => Http::response([
            'statusCode' => '1001',
            'statusMessage' => 'Insufficient balance',
        ], 200),
    ]);

    $order = createBkashOrder();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'gateway' => 'bkash',
        'status' => 'pending',
        'amount' => $order->total,
    ]);

    $gateway = new BkashGateway;
    $gateway->initiatePayment($order, $payment);
})->throws(RuntimeException::class, 'Insufficient balance');

// --- verifyPayment (Execute) ---

it('verifies bKash payment via execute endpoint', function () {
    fakeBkashTokenGrant();
    fakeBkashExecute('Completed');

    $order = createBkashOrder();
    $payment = createBkashPayment($order);

    $gateway = new BkashGateway;
    $result = $gateway->verifyPayment($payment, ['paymentID' => $payment->gateway_transaction_id]);

    expect($result)->toBeTrue();
});

it('returns false when execute returns non-completed status', function () {
    fakeBkashTokenGrant();
    fakeBkashExecute('Pending');

    $order = createBkashOrder();
    $payment = createBkashPayment($order);

    $gateway = new BkashGateway;
    $result = $gateway->verifyPayment($payment, ['paymentID' => $payment->gateway_transaction_id]);

    expect($result)->toBeFalse();
});

it('returns false when execute returns error status', function () {
    fakeBkashTokenGrant();

    Http::fake([
        'tokenized.sandbox.bka.sh/*execute/*' => Http::response([
            'statusCode' => '2001',
            'statusMessage' => 'Payment already processed',
        ], 200),
    ]);

    $order = createBkashOrder();
    $payment = createBkashPayment($order);

    $gateway = new BkashGateway;
    $result = $gateway->verifyPayment($payment, ['paymentID' => $payment->gateway_transaction_id]);

    expect($result)->toBeFalse();
});

it('returns false when execute throws exception', function () {
    fakeBkashTokenGrant();

    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $order = createBkashOrder();
    $payment = createBkashPayment($order);

    $gateway = new BkashGateway;
    $result = $gateway->verifyPayment($payment, ['paymentID' => $payment->gateway_transaction_id]);

    expect($result)->toBeFalse();
});

it('returns false when paymentID is empty', function () {
    $gateway = new BkashGateway;
    $payment = Payment::factory()->create();

    $result = $gateway->verifyPayment($payment, []);

    expect($result)->toBeFalse();
});

// --- queryPaymentStatus ---

it('queries payment status successfully', function () {
    fakeBkashTokenGrant();
    fakeBkashQuery('Completed');

    $order = createBkashOrder();
    $payment = createBkashPayment($order);

    $gateway = new BkashGateway;
    $result = $gateway->queryPaymentStatus($payment);

    expect($result)->toBeTrue();
});

it('returns false when query returns pending status', function () {
    fakeBkashTokenGrant();
    fakeBkashQuery('Pending');

    $order = createBkashOrder();
    $payment = createBkashPayment($order);

    $gateway = new BkashGateway;
    $result = $gateway->queryPaymentStatus($payment);

    expect($result)->toBeFalse();
});

it('returns false when query throws exception', function () {
    fakeBkashTokenGrant();

    Http::fake(fn () => throw new ConnectionException('Timeout'));

    $order = createBkashOrder();
    $payment = createBkashPayment($order);

    $gateway = new BkashGateway;
    $result = $gateway->queryPaymentStatus($payment);

    expect($result)->toBeFalse();
});

it('returns false when query has no gateway_transaction_id', function () {
    $gateway = new BkashGateway;
    $payment = Payment::factory()->create(['gateway_transaction_id' => null]);

    $result = $gateway->queryPaymentStatus($payment);

    expect($result)->toBeFalse();
});

// --- refund ---

it('refunds bKash payment successfully', function () {
    fakeBkashTokenGrant();
    fakeBkashRefund(true);

    $order = createBkashOrder();
    $payment = createBkashPayment($order, 'paid');

    $gateway = new BkashGateway;
    $result = $gateway->refund($payment, 500.0);

    expect($result)->toBeTrue();
});

it('returns false when refund fails', function () {
    fakeBkashTokenGrant();
    fakeBkashRefund(false);

    $order = createBkashOrder();
    $payment = createBkashPayment($order, 'paid');

    $gateway = new BkashGateway;
    $result = $gateway->refund($payment, 500.0);

    expect($result)->toBeFalse();
});

it('returns false when refund throws exception', function () {
    fakeBkashTokenGrant();

    Http::fake(fn () => throw new ConnectionException('Timeout'));

    $order = createBkashOrder();
    $payment = createBkashPayment($order, 'paid');

    $gateway = new BkashGateway;
    $result = $gateway->refund($payment, 500.0);

    expect($result)->toBeFalse();
});

// --- processWebhook ---

it('processes bkash success webhook', function () {
    $gateway = new BkashGateway;

    $result = $gateway->processWebhook([
        'paymentID' => 'BKASH_PAY_789',
        'status' => 'success',
    ]);

    expect($result['status'])->toBe('paid');
    expect($result['gateway_transaction_id'])->toBe('BKASH_PAY_789');
});

it('processes bkash failure webhook', function () {
    $gateway = new BkashGateway;

    $result = $gateway->processWebhook([
        'paymentID' => 'BKASH_PAY_000',
        'status' => 'failure',
    ]);

    expect($result['status'])->toBe('failed');
});

it('processes bkash cancel webhook', function () {
    $gateway = new BkashGateway;

    $result = $gateway->processWebhook([
        'paymentID' => 'BKASH_PAY_000',
        'status' => 'cancel',
    ]);

    expect($result['status'])->toBe('cancelled');
});

it('returns pending for unknown webhook status', function () {
    $gateway = new BkashGateway;

    $result = $gateway->processWebhook([
        'paymentID' => 'BKASH_PAY_000',
        'status' => 'unknown',
    ]);

    expect($result['status'])->toBe('pending');
});

// --- Full checkout flow ---

it('completes full bKash checkout flow with redirect', function () {
    config([
        'payment.gateways.bkash.app_key' => 'test_key',
        'payment.gateways.bkash.app_secret' => 'test_secret',
        'payment.gateways.bkash.username' => 'test_user',
        'payment.gateways.bkash.password' => 'test_pass',
        'payment.gateways.bkash.sandbox' => true,
    ]);

    fakeBkashTokenGrant();
    fakeBkashCreatePayment('BKASH_FLOW_TEST', 'https://sandbox.bka.sh/pay?token=abc');

    $user = User::factory()->create();
    $product = createBkashTestProduct(500, 50);
    $cart = createBkashTestCart($user, $product, 2);
    $rate = createBkashTestShippingRate();

    $response = $this->postJson('/api/checkout', [
        'shipping_name' => 'Test User',
        'phone' => '01712345678',
        'shipping_address' => '123 Test Street',
        'shipping_city' => 'Dhaka',
        'shipping_country' => 'Bangladesh',
        'shipping_rate_id' => $rate->id,
        'payment_method' => 'bkash',
    ], bkashAuthHeaders($user));

    $response->assertOk();

    $data = $response->json('data');
    expect($data['order']['status'])->toBe('pending');
    expect($data['payment']['redirect_url'])->toBe('https://sandbox.bka.sh/pay?token=abc');
    expect($data['payment']['id'])->not->toBeNull();

    $payment = Payment::find($data['payment']['id']);
    expect($payment->gateway_transaction_id)->toBe('BKASH_FLOW_TEST');
});

// --- Webhook handler ---

it('handles bKash webhook successfully via POST', function () {
    $order = createBkashOrder('pending');
    $payment = createBkashPayment($order);
    $payment->update(['gateway_transaction_id' => 'BKASH_WEBHOOK_123']);

    fakeBkashTokenGrant();
    fakeBkashExecute('Completed');

    $response = $this->postJson('/api/payments/webhook/bkash', [
        'paymentID' => 'BKASH_WEBHOOK_123',
        'status' => 'success',
    ]);

    $response->assertOk()->assertJson(['status' => 'ok']);

    $payment->refresh();
    expect($payment->status)->toBe('paid');
    expect($payment->paid_at)->not->toBeNull();

    $order->refresh();
    expect($order->status)->toBe('confirmed');
});

it('returns 404 for webhook with unknown paymentID', function () {
    fakeBkashTokenGrant();

    $response = $this->postJson('/api/payments/webhook/bkash', [
        'paymentID' => 'NONEXISTENT_ID',
        'status' => 'success',
    ]);

    $response->assertStatus(404);
});

it('does not mark paid when webhook execute fails', function () {
    $order = createBkashOrder('pending');
    $payment = createBkashPayment($order);
    $payment->update(['gateway_transaction_id' => 'BKASH_FAIL_123']);

    fakeBkashTokenGrant();

    Http::fake([
        'tokenized.sandbox.bka.sh/*execute/*' => Http::response([
            'statusCode' => '2001',
            'statusMessage' => 'Already processed',
        ], 200),
    ]);

    $response = $this->postJson('/api/payments/webhook/bkash', [
        'paymentID' => 'BKASH_FAIL_123',
        'status' => 'success',
    ]);

    $response->assertOk()->assertJson(['status' => 'ok']);

    $payment->refresh();
    expect($payment->status)->toBe('pending');
});

it('returns ok if payment already paid (idempotent)', function () {
    $order = createBkashOrder('confirmed');
    $payment = createBkashPayment($order, 'paid');
    $payment->update(['gateway_transaction_id' => 'BKASH_PAID_123']);

    $response = $this->postJson('/api/payments/webhook/bkash', [
        'paymentID' => 'BKASH_PAID_123',
        'status' => 'success',
    ]);

    $response->assertOk()->assertJson(['status' => 'ok']);
});

// --- Callback handler ---

it('handles bKash callback with success and queries payment', function () {
    $order = createBkashOrder('pending');
    $payment = createBkashPayment($order);
    $payment->update(['gateway_transaction_id' => 'BKASH_CB_123']);

    fakeBkashTokenGrant();
    fakeBkashQuery('Completed');

    $response = $this->getJson('/api/payments/callback/bkash?paymentID=BKASH_CB_123&status=success&order='.$order->order_number);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/account/orders/'.$order->order_number);
    expect($response->headers->get('Location'))->toContain('payment=bkash_success');

    $payment->refresh();
    expect($payment->status)->toBe('paid');

    $order->refresh();
    expect($order->status)->toBe('confirmed');
});

it('redirects to pending when callback query shows not completed', function () {
    $order = createBkashOrder('pending');
    $payment = createBkashPayment($order);
    $payment->update(['gateway_transaction_id' => 'BKASH_CB_PENDING']);

    fakeBkashTokenGrant();
    fakeBkashQuery('Pending');

    $response = $this->getJson('/api/payments/callback/bkash?paymentID=BKASH_CB_PENDING&status=success&order='.$order->order_number);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('payment=bkash_pending');

    $payment->refresh();
    expect($payment->status)->toBe('pending');
});

it('handles bKash callback with failure status', function () {
    $user = User::factory()->create();
    $product = createBkashTestProduct(500, 50);
    $cart = createBkashTestCart($user, $product, 2);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'subtotal' => 1000,
        'total' => 1000,
        'shipping_name' => 'Test Customer',
        'shipping_phone' => '01712345678',
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 500,
    ]);

    $payment = createBkashPayment($order);
    $payment->update(['gateway_transaction_id' => 'BKASH_CB_FAIL']);

    $response = $this->getJson('/api/payments/callback/bkash?paymentID=BKASH_CB_FAIL&status=failure&order='.$order->order_number);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('payment=bkash_failure');

    $payment->refresh();
    expect($payment->status)->toBe('failed');
});

it('handles bKash callback with cancel status', function () {
    $user = User::factory()->create();
    $product = createBkashTestProduct(500, 50);
    $cart = createBkashTestCart($user, $product, 2);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'subtotal' => 1000,
        'total' => 1000,
        'shipping_name' => 'Test Customer',
        'shipping_phone' => '01712345678',
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 500,
    ]);

    $payment = createBkashPayment($order);
    $payment->update(['gateway_transaction_id' => 'BKASH_CB_CANCEL']);

    $response = $this->getJson('/api/payments/callback/bkash?paymentID=BKASH_CB_CANCEL&status=cancel&order='.$order->order_number);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('payment=bkash_cancel');

    $payment->refresh();
    expect($payment->status)->toBe('cancelled');
});

it('redirects to checkout when callback missing params', function () {
    $response = $this->getJson('/api/payments/callback/bkash');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('error=bkash_missing_params');
});

it('redirects to checkout when payment not found in callback', function () {
    $response = $this->getJson('/api/payments/callback/bkash?paymentID=NONEXISTENT&status=success&order=ORD_FAKE');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('error=bkash_payment_not_found');
});

it('returns success if payment already paid in callback (idempotent)', function () {
    $order = createBkashOrder('confirmed');
    $payment = createBkashPayment($order, 'paid');
    $payment->update(['gateway_transaction_id' => 'BKASH_CB_PAID']);

    $response = $this->getJson('/api/payments/callback/bkash?paymentID=BKASH_CB_PAID&status=success&order='.$order->order_number);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('payment=bkash_success');
});
