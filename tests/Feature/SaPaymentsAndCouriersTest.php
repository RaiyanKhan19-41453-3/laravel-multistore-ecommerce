<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Services\Couriers\CourierGatewayFactory;
use App\Services\Couriers\Gateways\AramexGateway;
use App\Services\Couriers\Gateways\SmsaGateway;
use App\Services\PaymentGateways\MoyasarGateway;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentGateways\StripeGateway;
use App\Services\PaymentGateways\TabbyGateway;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('resolves moyasar tabby stripe gateways', function () {
    expect(PaymentGatewayFactory::make('moyasar'))->toBeInstanceOf(MoyasarGateway::class);
    expect(PaymentGatewayFactory::make('tabby'))->toBeInstanceOf(TabbyGateway::class);
    expect(PaymentGatewayFactory::make('stripe'))->toBeInstanceOf(StripeGateway::class);
    expect(PaymentGatewayFactory::make('moyasar')->getName())->toBe('moyasar');
    expect(PaymentGatewayFactory::make('tabby')->getName())->toBe('tabby');
    expect(PaymentGatewayFactory::make('stripe')->getName())->toBe('stripe');
});

it('moyasar initiates payment and returns redirect', function () {
    config(['payment.gateways.moyasar.api_key' => 'sk_test_123', 'payment.gateways.moyasar.base_url' => 'https://api.moyasar.com']);

    Http::fake([
        'api.moyasar.com/v1/payments' => Http::response(['id' => 'pay_moy_123', 'url' => 'https://moyasar.com/pay/pay_moy_123', 'status' => 'initiated'], 201),
    ]);

    $order = Order::factory()->create(['total' => 250, 'order_number' => 'ORD-TEST-MOY']);
    $payment = Payment::factory()->for($order)->create(['method' => 'moyasar', 'gateway' => 'moyasar', 'amount' => 250]);

    $gateway = new MoyasarGateway;
    $result = $gateway->initiatePayment($order, $payment);

    expect($result['redirect_url'])->toBe('https://moyasar.com/pay/pay_moy_123');
    expect($payment->fresh()->gateway_transaction_id)->toBe('pay_moy_123');
});

it('moyasar webhook maps paid', function () {
    $gateway = new MoyasarGateway;
    $result = $gateway->processWebhook(['id' => 'pay_moy_123', 'status' => 'paid']);
    expect($result['status'])->toBe('paid')->and($result['gateway_transaction_id'])->toBe('pay_moy_123');
});

it('moyasar verify checks api', function () {
    config(['payment.gateways.moyasar.api_key' => 'sk_test_123', 'payment.gateways.moyasar.base_url' => 'https://api.moyasar.com']);
    Http::fake([
        'api.moyasar.com/v1/payments/pay_moy_123' => Http::response(['id' => 'pay_moy_123', 'status' => 'paid', 'amount' => 25000], 200),
    ]);

    $order = Order::factory()->create(['total' => 250]);
    $payment = Payment::factory()->for($order)->create(['gateway' => 'moyasar', 'gateway_transaction_id' => 'pay_moy_123', 'amount' => 250]);

    expect((new MoyasarGateway)->verifyPayment($payment, ['id' => 'pay_moy_123']))->toBeTrue();
});

it('tabby initiates checkout', function () {
    config(['payment.gateways.tabby.secret_key' => 'sk_test_tabby', 'payment.gateways.tabby.public_key' => 'pk_test', 'payment.gateways.tabby.merchant_code' => 'MCODE', 'payment.gateways.tabby.base_url' => 'https://api.tabby.ai']);

    Http::fake([
        'api.tabby.ai/api/v2/checkout' => Http::response(['id' => 'tabby_123', 'status' => 'created', 'web_url' => 'https://checkout.tabby.ai/tabby_123'], 201),
    ]);

    $order = Order::factory()->create(['total' => 300, 'order_number' => 'ORD-TABBY-1']);
    OrderItem::factory()->for($order)->create(['unit_price' => 300, 'quantity' => 1, 'total' => 300]);
    $payment = Payment::factory()->for($order)->create(['method' => 'tabby', 'gateway' => 'tabby', 'amount' => 300]);

    $gateway = new TabbyGateway;
    $result = $gateway->initiatePayment($order->load('items'), $payment);

    expect($result['redirect_url'])->toBe('https://checkout.tabby.ai/tabby_123');
});

it('tabby webhook maps authorized to paid', function () {
    $gateway = new TabbyGateway;
    expect($gateway->processWebhook(['id' => 'tabby_123', 'status' => 'authorized'])['status'])->toBe('paid');
    expect($gateway->processWebhook(['id' => 'tabby_123', 'status' => 'rejected'])['status'])->toBe('failed');
});

it('tabby refund fails closed when the response carries no status', function () {
    config(['payment.gateways.tabby.secret_key' => 'sk_test_tabby', 'payment.gateways.tabby.public_key' => 'pk_test', 'payment.gateways.tabby.merchant_code' => 'MCODE', 'payment.gateways.tabby.base_url' => 'https://api.tabby.ai']);
    Http::fake(['api.tabby.ai/api/v2/payments/*/refunds' => Http::response([], 200)]);

    $order = Order::factory()->create(['total' => 300, 'order_number' => 'ORD-TABBY-REF']);
    $payment = Payment::factory()->for($order)->create([
        'method' => 'tabby',
        'gateway' => 'tabby',
        'status' => 'paid',
        'paid_at' => now(),
        'amount' => 300,
        'gateway_transaction_id' => 'tabby_123',
    ]);

    // A 200 with no status field proves nothing was refunded: fail closed
    // like the Stripe and Moyasar gateways do.
    expect((new TabbyGateway)->refund($payment, 300.0))->toBeFalse();
    expect($payment->fresh()->status)->toBe('paid');
});

it('stripe initiates checkout session', function () {
    config(['payment.gateways.stripe.secret_key' => 'sk_test_stripe', 'payment.gateways.stripe.base_url' => 'https://api.stripe.com']);

    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/pay/cs_test_123', 'payment_status' => 'unpaid', 'status' => 'open'], 200),
    ]);

    $order = Order::factory()->create(['total' => 500, 'order_number' => 'ORD-STRIPE-1']);
    $payment = Payment::factory()->for($order)->create(['method' => 'stripe', 'gateway' => 'stripe', 'amount' => 500]);

    $gateway = new StripeGateway;
    $result = $gateway->initiatePayment($order, $payment);

    expect($result['redirect_url'])->toBe('https://checkout.stripe.com/pay/cs_test_123');
});

it('stripe success and cancel urls target the get order-confirmation page', function () {
    Http::preventStrayRequests();
    config(['payment.gateways.stripe.secret_key' => 'sk_test_stripe', 'payment.gateways.stripe.base_url' => 'https://api.stripe.com']);

    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/pay/cs_test_123'], 200),
    ]);

    $store = Store::factory()->create(['slug' => 'stripe-shop']);
    $order = Order::factory()->create(['total' => 500, 'order_number' => 'ORD-STRIPE-URL', 'store_id' => $store->id]);
    $payment = Payment::factory()->for($order)->create(['method' => 'stripe', 'gateway' => 'stripe', 'amount' => 500]);

    $gateway = new StripeGateway;
    $gateway->initiatePayment($order, $payment);

    $payload = collect(Http::recorded(fn ($request) => str_contains($request->url(), 'api.stripe.com/v1/checkout/sessions')))
        ->map(fn ($pair) => $pair[0]->data())
        ->first();

    expect($payload['success_url'])->toBe(config('app.url').'/order-confirmation/ORD-STRIPE-URL?store=stripe-shop&status=success&session_id={CHECKOUT_SESSION_ID}')
        ->and($payload['cancel_url'])->toBe(config('app.url').'/order-confirmation/ORD-STRIPE-URL?store=stripe-shop&status=cancel');

    foreach (['success_url', 'cancel_url'] as $key) {
        $this->get(str_replace('{CHECKOUT_SESSION_ID}', 'cs_test_123', $payload[$key]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/order-confirmation')
                ->where('orderNumber', 'ORD-STRIPE-URL')
                ->where('store.slug', 'stripe-shop')
            );
    }

    expect($payment->fresh()->status)->toBe('pending');
});

it('order confirmation page renders with the requested order and store', function () {
    Store::factory()->create(['slug' => 'confirm-shop']);

    $this->get('/order-confirmation/ORD-CONF-1?store=confirm-shop&status=success&session_id=cs_test_123')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/order-confirmation')
            ->where('orderNumber', 'ORD-CONF-1')
            ->where('store.slug', 'confirm-shop')
        );
});

it('stripe webhook maps checkout.session.completed to paid', function () {
    $gateway = new StripeGateway;
    $result = $gateway->processWebhook(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_123', 'payment_status' => 'paid']]]);
    expect($result['status'])->toBe('paid')->and($result['gateway_transaction_id'])->toBe('cs_test_123');
});

it('verifies a completed stripe session using its stored session id rather than the event id', function () {
    Http::preventStrayRequests();
    config(['payment.gateways.stripe.base_url' => 'https://api.stripe.com']);
    $order = Order::factory()->pending()->create(['total' => 500]);
    $payment = Payment::factory()->for($order)->create([
        'gateway' => 'stripe',
        'gateway_transaction_id' => 'cs_test_completed',
        'amount' => 500,
    ]);
    Http::fake([
        'api.stripe.com/v1/checkout/sessions/cs_test_completed' => Http::response([
            'id' => 'cs_test_completed',
            'payment_status' => 'paid',
            'status' => 'complete',
        ]),
    ]);

    $verified = (new StripeGateway)->verifyPayment($payment, [
        'id' => 'evt_test_completed',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_completed', 'payment_status' => 'paid']],
    ]);

    expect($verified)->toBeTrue();
    Http::assertSentCount(1);
});

it('tabby return urls target the get order-confirmation page', function () {
    Http::preventStrayRequests();
    config(['payment.gateways.tabby.secret_key' => 'sk_test_tabby', 'payment.gateways.tabby.base_url' => 'https://api.tabby.ai']);

    Http::fake([
        'api.tabby.ai/api/v2/checkout' => Http::response(['id' => 'tabby_123', 'web_url' => 'https://checkout.tabby.ai/tabby_123'], 201),
    ]);

    $store = Store::factory()->create(['slug' => 'tabby-shop']);
    $order = Order::factory()->create(['total' => 300, 'order_number' => 'ORD-TABBY-URL', 'store_id' => $store->id]);
    OrderItem::factory()->for($order)->create(['unit_price' => 300, 'quantity' => 1, 'total' => 300]);
    $payment = Payment::factory()->for($order)->create(['method' => 'tabby', 'gateway' => 'tabby', 'amount' => 300]);

    (new TabbyGateway)->initiatePayment($order->load('items'), $payment);

    $payload = collect(Http::recorded(fn ($request) => str_contains($request->url(), 'api.tabby.ai/api/v2/checkout')))
        ->map(fn ($pair) => $pair[0]->data())
        ->first();

    expect($payload['merchant_urls']['success'])->toBe(config('app.url').'/order-confirmation/ORD-TABBY-URL?store=tabby-shop&status=success')
        ->and($payload['merchant_urls']['cancel'])->toBe(config('app.url').'/order-confirmation/ORD-TABBY-URL?store=tabby-shop&status=cancel')
        ->and($payload['merchant_urls']['failure'])->toBe(config('app.url').'/order-confirmation/ORD-TABBY-URL?store=tabby-shop&status=failure');

    foreach (['success', 'cancel', 'failure'] as $key) {
        $this->get($payload['merchant_urls'][$key])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/order-confirmation')
                ->where('orderNumber', 'ORD-TABBY-URL')
                ->where('store.slug', 'tabby-shop')
            );
    }

    expect($payment->fresh()->status)->toBe('pending');
});

it('moyasar callback url targets the get callback endpoint', function () {
    Http::preventStrayRequests();
    config(['payment.gateways.moyasar.api_key' => 'sk_test_123', 'payment.gateways.moyasar.base_url' => 'https://api.moyasar.com']);

    Http::fake([
        'api.moyasar.com/v1/payments' => Http::response(['id' => 'pay_moy_123', 'url' => 'https://moyasar.com/pay/pay_moy_123', 'status' => 'initiated'], 201),
    ]);

    $order = Order::factory()->create(['total' => 250, 'order_number' => 'ORD-TEST-MOY']);
    $payment = Payment::factory()->for($order)->create(['method' => 'moyasar', 'gateway' => 'moyasar', 'amount' => 250]);

    (new MoyasarGateway)->initiatePayment($order, $payment);

    $payload = collect(Http::recorded(fn ($request) => str_contains($request->url(), 'api.moyasar.com/v1/payments')))
        ->map(fn ($pair) => $pair[0]->data())
        ->first();

    expect($payload['callback_url'])->toBe(route('payments.callback.moyasar'));

    $this->get(route('payments.callback.moyasar', ['id' => 'NONEXISTENT']))
        ->assertRedirect('/checkout?error=moyasar_payment_not_found');
});

it('moyasar callback verifies payment and confirms the order', function () {
    Http::preventStrayRequests();
    config([
        'payment.gateways.moyasar.api_key' => 'sk_test_123',
        'payment.gateways.moyasar.base_url' => 'https://api.moyasar.com',
    ]);

    Http::fake([
        'api.moyasar.com/v1/payments/pay_moy_123' => Http::response(['id' => 'pay_moy_123', 'status' => 'paid', 'amount' => 25000], 200),
    ]);

    $order = Order::factory()->pending()->create(['total' => 250]);
    $payment = Payment::factory()->for($order)->create(['gateway' => 'moyasar', 'method' => 'moyasar', 'gateway_transaction_id' => 'pay_moy_123', 'amount' => 250]);

    $this->get(route('payments.callback.moyasar', ['id' => 'pay_moy_123']))
        ->assertRedirect('/order-confirmation/'.$order->order_number.'?payment=moyasar_success');

    expect($payment->fresh()->status)->toBe('paid');
    expect($order->fresh()->status)->toBe('confirmed');
});

it('moyasar callback leaves the order pending when verification fails', function () {
    Http::preventStrayRequests();
    config([
        'payment.gateways.moyasar.api_key' => 'sk_test_123',
        'payment.gateways.moyasar.base_url' => 'https://api.moyasar.com',
    ]);

    Http::fake(['api.moyasar.com/*' => Http::response([], 500)]);

    $order = Order::factory()->pending()->create(['total' => 250]);
    $payment = Payment::factory()->for($order)->create(['gateway' => 'moyasar', 'method' => 'moyasar', 'gateway_transaction_id' => 'pay_moy_456', 'amount' => 250]);

    $this->get(route('payments.callback.moyasar', ['id' => 'pay_moy_456']))
        ->assertRedirect('/order-confirmation/'.$order->order_number.'?payment=moyasar_pending');

    expect($payment->fresh()->status)->toBe('pending');
    expect($order->fresh()->status)->toBe('pending');
});

it('payment-methods exposes moyasar tabby stripe when enabled', function () {
    config([
        'payment.enabled.cod' => false,
        'payment.enabled.sslcommerz' => false,
        'payment.enabled.bkash' => false,
        'payment.enabled.moyasar' => true,
        'payment.enabled.tabby' => true,
        'payment.enabled.stripe' => true,
    ]);

    $response = $this->getJson('/api/payment-methods');
    $response->assertOk();
    $methods = collect($response->json('data.methods'))->pluck('value')->all();
    expect($methods)->toContain('moyasar')->toContain('tabby')->toContain('stripe');
    expect($response->json('data.methods')[0]['label'])->not->toBeEmpty();
});

it('checkout accepts moyasar when enabled', function () {
    config([
        'payment.enabled.moyasar' => true,
        'payment.enabled.cod' => true,
        'payment.enabled.sslcommerz' => false,
        'payment.enabled.tabby' => false,
        'payment.enabled.stripe' => false,
        'payment.gateways.moyasar.api_key' => 'sk_test_123',
        'payment.gateways.moyasar.base_url' => 'https://api.moyasar.com',
    ]);

    Http::fake([
        'api.moyasar.com/v1/payments' => Http::response(['id' => 'pay_moy_999', 'url' => 'https://moyasar.com/pay/pay_moy_999'], 201),
    ]);

    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(10)->create();
    $cart = Cart::create(['user_id' => $user->id, 'status' => 'active']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
    createTestShippingRate();

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'moyasar',
    ], authHeaders($user));

    $response->assertOk()->assertJson(['success' => true]);
    expect($response->json('data.payment.redirect_url'))->toBe('https://moyasar.com/pay/pay_moy_999');
});

it('payment enabled can be overridden via settings', function () {
    config(['payment.enabled.moyasar' => false]);
    expect(PaymentGatewayFactory::isEnabled('moyasar'))->toBeFalse();

    app(SettingsService::class)->set('payment.moyasar_enabled', '1', 'payment');
    expect(PaymentGatewayFactory::isEnabled('moyasar'))->toBeTrue();

    app(SettingsService::class)->set('payment.moyasar_enabled', '0', 'payment');
    expect(PaymentGatewayFactory::isEnabled('moyasar'))->toBeFalse();
});

it('sa preset enables sa gateways and bd preset enables bd gateways', function () {
    app(SettingsService::class)->applyPreset('SA');
    expect(PaymentGatewayFactory::isEnabled('moyasar'))->toBeTrue()
        ->and(PaymentGatewayFactory::isEnabled('tabby'))->toBeTrue()
        ->and(PaymentGatewayFactory::isEnabled('stripe'))->toBeTrue()
        ->and(PaymentGatewayFactory::isEnabled('sslcommerz'))->toBeFalse();

    app(SettingsService::class)->applyPreset('BD');
    expect(PaymentGatewayFactory::isEnabled('sslcommerz'))->toBeTrue()
        ->and(PaymentGatewayFactory::isEnabled('moyasar'))->toBeFalse()
        ->and(PaymentGatewayFactory::isEnabled('tabby'))->toBeFalse();
});

it('webhook handles moyasar success and idempotency', function () {
    $order = Order::factory()->pending()->create(['total' => 400]);
    $product = Product::factory()->create(['price' => 400, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(10)->create();
    OrderItem::factory()->for($order)->create(['product_id' => $product->id, 'unit_price' => 400, 'quantity' => 1, 'subtotal' => 400, 'total' => 400]);
    Inventory::where('product_id', $product->id)->increment('reserved_quantity', 1);

    $payment = Payment::factory()->for($order)->create(['gateway' => 'moyasar', 'method' => 'moyasar', 'gateway_transaction_id' => 'pay_moy_123', 'amount' => 400]);

    config(['payment.verify_webhooks' => false]);

    $payload = ['id' => 'pay_moy_123', 'status' => 'paid'];

    $this->postJson('/api/payments/webhook/moyasar', $payload)->assertOk();
    $this->postJson('/api/payments/webhook/moyasar', $payload)->assertOk();

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
});

it('smsa and aramex gateways resolve via factory', function () {
    config()->set('couriers.smsa.settings', ['api_key' => 'test-key']);
    config()->set('couriers.aramex.settings', ['account_number' => '123', 'username' => 'u', 'password' => 'p', 'account_pin' => 'pin']);

    expect(CourierGatewayFactory::make('smsa'))->toBeInstanceOf(SmsaGateway::class);
    expect(CourierGatewayFactory::make('aramex'))->toBeInstanceOf(AramexGateway::class);
    expect(CourierGatewayFactory::supportsApi('smsa'))->toBeTrue();
    expect(CourierGatewayFactory::supportsApi('aramex'))->toBeTrue();
});

it('smsa creates shipment via http fake', function () {
    Http::fake([
        'ecom.smsaexpress.com/api/shipment/b2c' => Http::response(['awb' => 'SMSA123456', 'consignmentId' => 'SMSA123456', 'price' => 25], 200),
    ]);

    config()->set('couriers.smsa.settings', ['api_key' => 'test-key']);
    $order = Order::factory()->create(['total' => 500, 'shipping_name' => 'Ahmed', 'shipping_phone' => '0501234567', 'shipping_city' => 'Riyadh', 'shipping_country' => 'SA']);
    OrderItem::factory()->for($order)->create();
    $shipment = Shipment::factory()->make();

    $gateway = CourierGatewayFactory::make('smsa');
    $result = $gateway->createShipment($order->load('items'), $shipment);

    expect($result['tracking_number'])->toBe('SMSA123456');
});

it('aramex creates shipment via http fake', function () {
    Http::fake([
        'ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CreateShipments' => Http::response(['HasErrors' => false, 'Shipments' => [['ID' => 'ARAMEX789', 'AWBNumber' => 'ARAMEX789']]], 200),
    ]);

    config()->set('couriers.aramex.settings', ['account_number' => '123', 'username' => 'u', 'password' => 'p', 'account_pin' => 'pin']);
    $order = Order::factory()->create(['total' => 600, 'shipping_name' => 'Ahmed', 'shipping_phone' => '0501234567', 'shipping_city' => 'Jeddah', 'shipping_country' => 'SA']);
    OrderItem::factory()->for($order)->create();
    $shipment = Shipment::factory()->make();

    $gateway = CourierGatewayFactory::make('aramex');
    $result = $gateway->createShipment($order->load('items'), $shipment);

    expect($result['tracking_number'])->toBe('ARAMEX789');
});

it('admin can update payment toggles via settings', function () {
    $admin = createAdmin();
    $this->actingAs($admin)->putJson('/admin/settings', [
        'payment' => ['moyasar_enabled' => true, 'tabby_enabled' => false],
    ])->assertRedirect('/admin/settings');

    expect(app(SettingsService::class)->get('payment.moyasar_enabled'))->toBe('1')
        ->and(app(SettingsService::class)->get('payment.tabby_enabled'))->toBe('0');
});
