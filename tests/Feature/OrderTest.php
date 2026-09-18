<?php

use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;

it('can list user orders', function () {
    $user = User::factory()->create();
    Order::factory()->count(3)->for($user)->create();

    $response = $this->actingAs($user)
        ->getJson('/api/orders');

    $response->assertOk()->assertJson([
        'success' => true,
    ]);

    expect($response->json('data.data'))->toHaveCount(3);
});

it('can view order detail', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->count(2)->for($order)->create();
    Payment::factory()->for($order)->paid()->create();

    $response = $this->actingAs($user)
        ->getJson("/api/orders/{$order->id}");

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'id' => $order->id,
            'order_number' => $order->order_number,
        ],
    ]);
});

it('cannot view other users order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($otherUser)->create();

    $response = $this->actingAs($user)
        ->getJson("/api/orders/{$order->id}");

    $response->assertStatus(404);
});

it('can cancel pending order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->pending()->for($user)->create();

    $response = $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Order cancelled.',
    ]);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'cancelled',
    ]);
});

it('can cancel confirmed order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->confirmed()->for($user)->create();

    $response = $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Order cancelled.',
    ]);
});

it('cannot cancel other users order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->pending()->for($otherUser)->create();

    $response = $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertStatus(403);
});

it('paginates user orders', function () {
    $user = User::factory()->create();
    Order::factory()->count(20)->for($user)->create();

    $response = $this->actingAs($user)
        ->getJson('/api/orders');

    $response->assertOk();

    expect($response->json('data.data'))->toHaveCount(15);
    expect($response->json('data.last_page'))->toBe(2);
});

it('restores inventory when cancelling confirmed order', function () {
    $user = User::factory()->create();
    $product = createProduct(500, 20);

    $order = Order::factory()->confirmed()->for($user)->create();
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $inventory = Inventory::where('product_id', $product->id)->first();
    $inventory->update(['quantity' => 17, 'reserved_quantity' => 5]);
    expect($inventory->fresh()->quantity)->toBe(17);

    $response = $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertOk();

    expect($inventory->fresh()->quantity)->toBe(20);
    expect($inventory->fresh()->reserved_quantity)->toBe(5);
});

it('rejects a repeated cancellation from a stale order without restoring stock twice', function () {
    $product = createProduct(500, 17);
    $order = Order::factory()->confirmed()->create();
    OrderItem::factory()->for($order)->create(['product_id' => $product->id, 'quantity' => 3]);
    $inventory = Inventory::where('product_id', $product->id)->first();
    $inventory->update(['reserved_quantity' => 5]);
    $staleOrder = $order->fresh();
    $service = app(OrderService::class);
    $service->cancel($order);

    expect(fn () => $service->cancel($staleOrder))->toThrow(InvalidArgumentException::class);

    expect($inventory->fresh()->quantity)->toBe(20);
    expect($inventory->fresh()->reserved_quantity)->toBe(5);
    expect($inventory->movements()->where('type', 'return')->count())->toBe(1);
});

it('cancels using the current order state after payment confirmation', function () {
    $product = createProduct(500, 20);
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->for($order)->create(['product_id' => $product->id, 'quantity' => 3]);
    $inventory = Inventory::where('product_id', $product->id)->first();
    $inventory->update(['reserved_quantity' => 8]);
    $payment = Payment::factory()->for($order)->paid()->create();
    $staleOrder = $order->fresh();
    $service = app(OrderService::class);
    $service->confirmPayment($order, $payment);

    $service->cancel($staleOrder);

    expect($order->fresh()->status)->toBe('cancelled');
    expect($inventory->fresh()->quantity)->toBe(20);
    expect($inventory->fresh()->reserved_quantity)->toBe(5);
});

it('only releases reservation when cancelling pending order', function () {

    $user = User::factory()->create();
    $product = createProduct(500, 20);

    $order = Order::factory()->pending()->for($user)->create();
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    Inventory::factory()->forProduct($product)->withQuantity(20)->create();

    $inventory = Inventory::where('product_id', $product->id)->first();
    $inventory->increment('reserved_quantity', 3);

    expect($inventory->quantity)->toBe(20);
    expect($inventory->reserved_quantity)->toBe(3);

    $response = $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertOk();

    $inventory->refresh();
    expect($inventory->quantity)->toBe(20);
    expect($inventory->reserved_quantity)->toBe(0);
});

it('does not expire or release stock for non-pending orders', function () {
    $user = User::factory()->create();
    $product = createProduct(500, 20);

    $order = Order::factory()->confirmed()->for($user)->create();
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $inventory = Inventory::where('product_id', $product->id)->first();
    $inventory->update(['quantity' => 17, 'reserved_quantity' => 0]);

    app(OrderService::class)->expireOrder($order);

    expect($order->fresh()->status)->toBe('confirmed');
    expect($inventory->fresh()->quantity)->toBe(17);
    expect($inventory->fresh()->reserved_quantity)->toBe(0);
});

it('cancels order with soft-deleted coupon without crashing', function () {
    $user = User::factory()->create();
    $product = createProduct(500, 20);

    $discount = Discount::factory()->fixed()->create(['value' => 50, 'is_active' => true]);
    $coupon = Coupon::factory()->for($discount)->create([
        'code' => 'GONECOUPON',
        'is_active' => true,
    ]);

    $order = Order::factory()->confirmed()->for($user)->create(['coupon_id' => $coupon->id]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $coupon->delete();

    $response = $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertOk();
    expect($order->fresh()->status)->toBe('cancelled');
});

it('hides gateway internals on order detail', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    Payment::factory()->for($order)->paid()->create(['gateway_response' => ['secret' => 'abc']]);

    $response = $this->actingAs($user)->getJson("/api/orders/{$order->id}");

    $response->assertOk();
    expect($response->json('data.payments.0.gateway_response'))->toBeNull();
});

it('hides gateway internals on order listing', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    Payment::factory()->for($order)->paid()->create(['gateway_response' => ['secret' => 'abc']]);

    $response = $this->actingAs($user)->getJson('/api/orders');

    $response->assertOk();
    expect($response->json('data.data.0.payments.0.gateway_response'))->toBeNull();
});
