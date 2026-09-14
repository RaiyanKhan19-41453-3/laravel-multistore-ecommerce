<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

function createOrderWithItems(string $status = 'pending'): Order
{
    $order = Order::factory()->create([
        'status' => $status,
        'subtotal' => 1000,
        'total' => 1000,
    ]);
    OrderItem::factory()->count(2)->for($order)->create();

    return $order;
}

it('can list orders as admin', function () {
    $admin = createAdmin();
    createOrderWithItems();
    createOrderWithItems('confirmed');

    $response = $this->actingAs($admin)
        ->get('/admin/orders');

    $response->assertOk();
    $response->assertStatus(200);
});

it('can filter orders by status', function () {
    $admin = createAdmin();
    createOrderWithItems('pending');
    createOrderWithItems('confirmed');
    createOrderWithItems('confirmed');

    $response = $this->actingAs($admin)
        ->get('/admin/orders?status=confirmed');

    $response->assertOk();
});

it('can search orders by order number', function () {
    $admin = createAdmin();
    $order = Order::factory()->create(['order_number' => 'ORD-20260101-ABC123']);
    OrderItem::factory()->count(2)->for($order)->create();

    $response = $this->actingAs($admin)
        ->get('/admin/orders?search=ABC123');

    $response->assertOk();
});

it('can show order detail as admin', function () {
    $admin = createAdmin();
    $order = createOrderWithItems();

    $response = $this->actingAs($admin)
        ->get("/admin/orders/{$order->id}");

    $response->assertOk();
});

it('can update order status through valid transition', function () {
    $admin = createAdmin();
    $order = createOrderWithItems('pending');

    $response = $this->actingAs($admin)
        ->post("/admin/orders/{$order->id}/status", ['status' => 'confirmed']);

    $response->assertRedirect();
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'confirmed',
    ]);
});

it('rejects invalid status transition', function () {
    $admin = createAdmin();
    $order = createOrderWithItems('pending');

    $response = $this->actingAs($admin)
        ->post("/admin/orders/{$order->id}/status", ['status' => 'delivered']);

    $response->assertStatus(302);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'pending',
    ]);
});

it('requires admin role to manage orders', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/admin/orders');

    $response->assertForbidden();
});

it('paginates orders', function () {
    $admin = createAdmin();
    Order::factory()->count(20)->create();

    $response = $this->actingAs($admin)
        ->get('/admin/orders');

    $response->assertOk();
});
