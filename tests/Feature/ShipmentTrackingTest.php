<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\User;

function createShipmentAdmin(): User
{
    ensureStaffPermissions();

    $user = User::factory()->create();
    $user->assignRole('super-admin');

    return $user;
}

function createShipmentOrder(string $status = 'pending'): Order
{
    $order = Order::factory()->create([
        'status' => $status,
        'subtotal' => 1000,
        'total' => 1000,
    ]);
    OrderItem::factory()->count(2)->for($order)->create();

    return $order;
}

it('admin can add a shipment to an order', function () {
    $admin = createShipmentAdmin();
    $order = createShipmentOrder('confirmed');

    $response = $this->actingAs($admin)
        ->post("/admin/orders/{$order->id}/shipments", [
            'courier_code' => 'pathao',
            'tracking_number' => 'PTH-98765',
            'note' => 'Handle with care',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('shipments', [
        'order_id' => $order->id,
        'courier_code' => 'pathao',
        'courier' => config('couriers.pathao.name'),
        'tracking_number' => 'PTH-98765',
        'status' => 'pending',
        'note' => 'Handle with care',
    ]);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'fulfillment_type' => 'courier',
    ]);
});

it('admin can update a shipment status', function () {
    $admin = createShipmentAdmin();
    $order = createShipmentOrder('confirmed');
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => 'pending',
        'courier' => 'Pathao',
        'tracking_number' => 'PTH-000',
    ]);

    $response = $this->actingAs($admin)
        ->put("/admin/orders/{$order->id}/shipments/{$shipment->id}", [
            'status' => 'in_transit',
            'tracking_number' => 'PTH-111',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('shipments', [
        'id' => $shipment->id,
        'status' => 'in_transit',
        'tracking_number' => 'PTH-111',
    ]);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'shipped',
    ]);
});

it('marks order delivered when shipment status is delivered', function () {
    $admin = createShipmentAdmin();
    $order = createShipmentOrder('shipped');
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => 'in_transit',
    ]);

    $response = $this->actingAs($admin)
        ->put("/admin/orders/{$order->id}/shipments/{$shipment->id}", [
            'status' => 'delivered',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'delivered',
    ]);
    $this->assertNotNull($order->fresh()->delivered_at);
});

it('admin can delete a shipment', function () {
    $admin = createShipmentAdmin();
    $order = createShipmentOrder('confirmed');
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
    ]);

    $response = $this->actingAs($admin)
        ->delete("/admin/orders/{$order->id}/shipments/{$shipment->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('shipments', ['id' => $shipment->id]);
});

it('shipment requires courier and tracking number', function () {
    $admin = createShipmentAdmin();
    $order = createShipmentOrder('confirmed');

    $response = $this->actingAs($admin)
        ->post("/admin/orders/{$order->id}/shipments", [
            'courier_code' => '',
            'tracking_number' => '',
        ]);

    $response->assertSessionHasErrors(['courier_code', 'tracking_number']);
});

it('shipment update requires valid status', function () {
    $admin = createShipmentAdmin();
    $order = createShipmentOrder('confirmed');
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    $response = $this->actingAs($admin)
        ->put("/admin/orders/{$order->id}/shipments/{$shipment->id}", [
            'status' => 'invalid_status',
        ]);

    $response->assertSessionHasErrors(['status']);
});

it('order show includes shipments', function () {
    $admin = createShipmentAdmin();
    $order = createShipmentOrder('confirmed');
    Shipment::factory()->create(['order_id' => $order->id]);

    $response = $this->actingAs($admin)
        ->get("/admin/orders/{$order->id}");

    $response->assertOk();
});

it('requires admin role to manage shipments', function () {
    $user = User::factory()->create();
    $order = createShipmentOrder('confirmed');

    $response = $this->actingAs($user)
        ->post("/admin/orders/{$order->id}/shipments", [
            'courier_code' => 'pathao',
            'tracking_number' => 'PTH-000',
        ]);

    $response->assertForbidden();
});
