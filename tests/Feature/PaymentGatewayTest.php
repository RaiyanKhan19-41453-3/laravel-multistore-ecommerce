<?php

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentGateways\BkashGateway;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentGateways\SSLCommerzGateway;

it('can get enabled payment gateways', function () {
    config(['payment.enabled.cod' => true]);
    config(['payment.enabled.sslcommerz' => true]);
    config(['payment.enabled.bkash' => false]);

    $enabled = PaymentGatewayFactory::getEnabled();

    expect($enabled)->toContain('cod')->toContain('sslcommerz')->not->toContain('bkash');
});

it('can check if gateway is enabled', function () {
    config(['payment.enabled.cod' => true]);
    config(['payment.enabled.bkash' => false]);

    expect(PaymentGatewayFactory::isEnabled('cod'))->toBeTrue();
    expect(PaymentGatewayFactory::isEnabled('bkash'))->toBeFalse();
});

it('can resolve sslcommerz gateway', function () {
    $gateway = PaymentGatewayFactory::make('sslcommerz');

    expect($gateway)->toBeInstanceOf(SSLCommerzGateway::class);
    expect($gateway->getName())->toBe('sslcommerz');
});

it('can resolve bkash gateway', function () {
    $gateway = PaymentGatewayFactory::make('bkash');

    expect($gateway)->toBeInstanceOf(BkashGateway::class);
    expect($gateway->getName())->toBe('bkash');
});

it('returns null for unknown gateway', function () {
    $gateway = PaymentGatewayFactory::make('unknown');

    expect($gateway)->toBeNull();
});

it('can process bkash webhook payload', function () {
    $gateway = new BkashGateway;

    $result = $gateway->processWebhook([
        'status' => 'success',
        'paymentID' => 'PAY_123456',
    ]);

    expect($result['status'])->toBe('paid');
    expect($result['gateway_transaction_id'])->toBe('PAY_123456');
});

it('handles bkash failure webhook', function () {
    $gateway = new BkashGateway;

    $result = $gateway->processWebhook([
        'status' => 'failure',
        'paymentID' => 'PAY_789',
    ]);

    expect($result['status'])->toBe('failed');
});

it('handles bkash cancel webhook', function () {
    $gateway = new BkashGateway;

    $result = $gateway->processWebhook([
        'status' => 'cancel',
        'paymentID' => 'PAY_456',
    ]);

    expect($result['status'])->toBe('cancelled');
});

it('returns pending for unknown bkash status', function () {
    $gateway = new BkashGateway;

    $result = $gateway->processWebhook([
        'status' => 'unknown',
        'paymentID' => 'PAY_999',
    ]);

    expect($result['status'])->toBe('pending');
});

it('verifies bkash payment fails without paymentID', function () {
    $gateway = new BkashGateway;
    $payment = Payment::factory()->create();

    $result = $gateway->verifyPayment($payment, []);

    expect($result)->toBeFalse();
});

it('can fetch payment methods from API', function () {
    config(['payment.enabled.cod' => true]);
    config(['payment.enabled.sslcommerz' => true]);
    config(['payment.enabled.bkash' => false]);

    $response = $this->getJson('/api/payment-methods');

    $response->assertOk()
        ->assertJsonStructure(['success', 'data' => ['methods' => [['value', 'label']]]]);

    $methods = $response->json('data.methods');
    expect($methods)->toHaveCount(2);
    expect($methods[0]['value'])->toBe('cod');
    expect($methods[1]['value'])->toBe('sslcommerz');
});

it('only returns enabled payment methods', function () {
    config(['payment.enabled.cod' => false]);
    config(['payment.enabled.sslcommerz' => true]);
    config(['payment.enabled.bkash' => false]);

    $response = $this->getJson('/api/payment-methods');

    $response->assertOk();

    $methods = $response->json('data.methods');
    expect($methods)->toHaveCount(1);
    expect($methods[0]['value'])->toBe('sslcommerz');
});

it('excludes enabled flags for gateways with no implementation', function () {
    config(['payment.enabled.cod' => true]);
    config(['payment.enabled.nagad' => true]);
    config(['payment.enabled.rocket' => true]);

    $enabled = PaymentGatewayFactory::getEnabled();

    expect($enabled)->toContain('cod')->not->toContain('nagad')->not->toContain('rocket');
});

it('rejects unimplemented gateway methods at checkout validation', function () {
    config(['payment.enabled.nagad' => true]);

    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'nagad',
    ], authHeaders($user));

    $response->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
    expect(Order::count())->toBe(0);
});
