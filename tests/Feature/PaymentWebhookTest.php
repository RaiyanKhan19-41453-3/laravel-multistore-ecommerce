<?php

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;

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
