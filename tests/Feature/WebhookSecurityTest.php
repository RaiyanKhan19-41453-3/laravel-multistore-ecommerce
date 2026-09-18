<?php

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentGateways\StripeGateway;
use App\Services\PaymentGateways\TabbyGateway;
use Illuminate\Support\Facades\Http;

function stripeTestPayment(Order $order): Payment
{
    return Payment::factory()->for($order)->create([
        'method' => 'stripe',
        'gateway' => 'stripe',
        'gateway_transaction_id' => 'cs_test_forged',
        'amount' => 500,
    ]);
}

it('rejects a forged stripe payload when the api lookup fails', function () {
    config(['payment.gateways.stripe.secret_key' => 'sk_test_stripe', 'payment.gateways.stripe.base_url' => 'https://api.stripe.com']);
    Http::fake(['api.stripe.com/*' => Http::response([], 500)]);

    $order = Order::factory()->pending()->create();
    $payment = stripeTestPayment($order);

    $verified = (new StripeGateway)->verifyPayment($payment, [
        'id' => 'cs_test_forged',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_forged', 'payment_status' => 'paid']],
    ]);

    expect($verified)->toBeFalse();
});

it('does not confirm the order on a forged stripe webhook when lookup fails', function () {
    config(['payment.verify_webhooks' => true]);
    config(['payment.gateways.stripe.secret_key' => 'sk_test_stripe', 'payment.gateways.stripe.base_url' => 'https://api.stripe.com']);
    Http::fake(['api.stripe.com/*' => Http::response([], 500)]);

    $order = Order::factory()->pending()->create();
    stripeTestPayment($order);

    $this->postJson('/api/payments/webhook/stripe', [
        'id' => 'cs_test_forged',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_forged', 'payment_status' => 'paid']],
    ])->assertOk();

    expect($order->fresh()->status)->toBe('pending');
});

it('rejects a forged tabby payload when the api lookup fails', function () {
    config(['payment.gateways.tabby.secret_key' => 'sk_test_tabby', 'payment.gateways.tabby.base_url' => 'https://api.tabby.ai']);
    Http::fake(['api.tabby.ai/*' => Http::response([], 500)]);

    $order = Order::factory()->pending()->create();
    $payment = Payment::factory()->for($order)->create([
        'method' => 'tabby',
        'gateway' => 'tabby',
        'gateway_transaction_id' => 'tabby_forged',
        'amount' => 300,
    ]);

    $verified = (new TabbyGateway)->verifyPayment($payment, ['id' => 'tabby_forged', 'status' => 'authorized']);

    expect($verified)->toBeFalse();
});

it('does not confirm the order on a forged tabby webhook when lookup fails', function () {
    config(['payment.verify_webhooks' => true]);
    config(['payment.gateways.tabby.secret_key' => 'sk_test_tabby', 'payment.gateways.tabby.base_url' => 'https://api.tabby.ai']);
    Http::fake(['api.tabby.ai/*' => Http::response([], 500)]);

    $order = Order::factory()->pending()->create();
    Payment::factory()->for($order)->create([
        'method' => 'tabby',
        'gateway' => 'tabby',
        'gateway_transaction_id' => 'tabby_forged',
        'amount' => 300,
    ]);

    $this->postJson('/api/payments/webhook/tabby', ['id' => 'tabby_forged', 'status' => 'authorized'])->assertOk();

    expect($order->fresh()->status)->toBe('pending');
});
