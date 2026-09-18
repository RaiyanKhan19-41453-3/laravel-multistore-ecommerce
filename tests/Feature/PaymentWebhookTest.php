<?php

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Log;

it('marks order confirmed on payment success webhook', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(20)->create();

    $order = Order::factory()->pending()->for($user)->create(['total' => 500]);
    $payment = Payment::factory()->for($order)->bkash()->create([
        'amount' => 500,
        'gateway' => 'sslcommerz',
    ]);

    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'unit_price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'total' => 500,
    ]);

    Inventory::where('product_id', $product->id)
        ->increment('reserved_quantity', 1);

    $response = $this->postJson('/api/payments/webhook/sslcommerz', [
        'status' => 'VALID',
        'tran_id' => $payment->id,
        'val_id' => 'VAL-123456',
    ]);

    $response->assertOk()->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'confirmed',
    ]);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => 'paid',
    ]);

    $inventory = Inventory::where('product_id', $product->id)->first();
    expect($inventory->quantity)->toBe(19);
    expect($inventory->reserved_quantity)->toBe(0);
});

it('webhook is idempotent', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(20)->create();

    $order = Order::factory()->pending()->for($user)->create(['total' => 500]);
    $payment = Payment::factory()->for($order)->bkash()->create([
        'amount' => 500,
        'gateway' => 'sslcommerz',
    ]);

    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'unit_price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'total' => 500,
    ]);

    Inventory::where('product_id', $product->id)
        ->increment('reserved_quantity', 1);

    $payload = [
        'status' => 'VALID',
        'tran_id' => $payment->id,
        'val_id' => 'VAL-123456',
    ];

    $this->postJson('/api/payments/webhook/sslcommerz', $payload)->assertOk();
    $this->postJson('/api/payments/webhook/sslcommerz', $payload)->assertOk();

    $inventory = Inventory::where('product_id', $product->id)->first();
    expect($inventory->quantity)->toBe(19);
    expect($inventory->reserved_quantity)->toBe(0);
});

it('handles failed payment webhook', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(20)->create();

    $order = Order::factory()->pending()->for($user)->create(['total' => 500]);
    $payment = Payment::factory()->for($order)->bkash()->create([
        'amount' => 500,
        'gateway' => 'sslcommerz',
    ]);

    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'unit_price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'total' => 500,
    ]);

    Inventory::where('product_id', $product->id)
        ->increment('reserved_quantity', 1);

    $response = $this->postJson('/api/payments/webhook/sslcommerz', [
        'status' => 'FAILED',
        'tran_id' => $payment->id,
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => 'failed',
    ]);

    $inventory = Inventory::where('product_id', $product->id)->first();
    expect($inventory->quantity)->toBe(20);
    expect($inventory->reserved_quantity)->toBe(0);
});

it('returns 404 for unknown payment webhook', function () {
    $response = $this->postJson('/api/payments/webhook/sslcommerz', [
        'status' => 'VALID',
        'tran_id' => 99999,
    ]);

    $response->assertStatus(404);
});

it('does not confirm order when webhook verification fails', function () {
    config(['payment.verify_webhooks' => true]);

    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(20)->create();

    $order = Order::factory()->pending()->for($user)->create(['total' => 500]);
    $payment = Payment::factory()->for($order)->create([
        'amount' => 500,
        'gateway' => 'sslcommerz',
        'method' => 'sslcommerz',
    ]);

    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'unit_price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'total' => 500,
    ]);

    $response = $this->postJson('/api/payments/webhook/sslcommerz', [
        'status' => 'VALID',
        'tran_id' => $payment->id,
    ]);

    $response->assertOk()->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'pending',
    ]);
});

it('returns 404 for unsupported webhook method', function () {
    $response = $this->postJson('/api/payments/webhook/nagad', [
        'status' => 'VALID',
    ]);

    $response->assertStatus(404);
});

it('flags paid money on an expired order for manual review instead of going silent', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(20)->create();

    $order = Order::factory()->expired()->for($user)->create(['total' => 500]);
    $payment = Payment::factory()->for($order)->bkash()->create([
        'amount' => 500,
        'gateway' => 'sslcommerz',
    ]);

    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'unit_price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'total' => 500,
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($message, $context) => $message === 'Payment received for non-pending order; manual review required'
            && ($context['order_id'] ?? null) === $order->id
            && ($context['payment_id'] ?? null) === $payment->id);

    $response = $this->postJson('/api/payments/webhook/sslcommerz', [
        'status' => 'VALID',
        'tran_id' => $payment->id,
        'val_id' => 'VAL-123456',
    ]);

    $response->assertOk()->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'expired']);
});

it('confirms payments for orders outside the resolved store', function () {
    config(['payment.verify_webhooks' => false]);

    $storeB = Store::factory()->create(['slug' => 'pay-b']);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(20)->create(['store_id' => $storeB->id]);

    $order = Order::factory()->pending()->for($user)->create(['total' => 500, 'store_id' => $storeB->id]);
    $payment = Payment::factory()->for($order)->create([
        'amount' => 500,
        'gateway' => 'sslcommerz',
        'method' => 'sslcommerz',
        'store_id' => $storeB->id,
    ]);

    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'unit_price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'total' => 500,
    ]);

    Inventory::where('product_id', $product->id)->increment('reserved_quantity', 1);

    // No store header: the webhook resolves the default store, but the
    // payment and order live in B. Gateway ids are globally unique, so
    // the lookup must not be scoped to the resolved store.
    $response = $this->postJson('/api/payments/webhook/sslcommerz', [
        'status' => 'VALID',
        'tran_id' => $payment->id,
        'val_id' => 'VAL-123456',
    ]);

    $response->assertOk()->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
});
