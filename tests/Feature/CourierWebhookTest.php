<?php

use App\Models\Courier;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;

function createWebhookCourier(string $code): Courier
{
    return Courier::firstOrCreate(
        ['code' => $code],
        ['name' => ucfirst(str_replace('_', ' ', $code)), 'is_active' => true, 'sort_order' => 0]
    );
}

function createWebhookOrder(string $status = 'pending'): Order
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

function createWebhookShipment(Order $order, Courier $courier, string $trackingId): Shipment
{
    return Shipment::factory()->create([
        'order_id' => $order->id,
        'courier_id' => $courier->id,
        'courier_order_id' => $trackingId,
        'tracking_number' => $trackingId,
        'status' => 'pending',
    ]);
}

// --- RedX (official: tracking_number, status) ---

it('handles redx delivered webhook', function () {
    $courier = createWebhookCourier('redx');
    $order = createWebhookOrder('shipped');
    $shipment = createWebhookShipment($order, $courier, 'RX_123456');

    $response = $this->postJson('/api/webhooks/couriers/redx', [
        'tracking_number' => 'RX_123456',
        'status' => 'delivered',
        'timestamp' => now()->toDateTimeString(),
        'message_en' => 'Parcel delivered',
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $shipment->refresh();
    expect($shipment->status)->toBe('delivered');

    $order->refresh();
    expect($order->status)->toBe('delivered');
});

it('handles redx delivery-in-progress webhook', function () {
    $courier = createWebhookCourier('redx');
    $order = createWebhookOrder('pending');
    $shipment = createWebhookShipment($order, $courier, 'RX_789');

    $response = $this->postJson('/api/webhooks/couriers/redx', [
        'tracking_number' => 'RX_789',
        'status' => 'delivery-in-progress',
    ]);

    $response->assertOk();

    $shipment->refresh();
    expect($shipment->status)->toBe('out_for_delivery');
});

it('handles redx ready-for-delivery webhook', function () {
    $courier = createWebhookCourier('redx');
    $order = createWebhookOrder('pending');
    $shipment = createWebhookShipment($order, $courier, 'RX_READY');

    $response = $this->postJson('/api/webhooks/couriers/redx', [
        'tracking_number' => 'RX_READY',
        'status' => 'ready-for-delivery',
    ]);

    $response->assertOk();

    $shipment->refresh();
    expect($shipment->status)->toBe('picked');
});

it('handles redx agent-returning webhook', function () {
    $courier = createWebhookCourier('redx');
    $order = createWebhookOrder('pending');
    $shipment = createWebhookShipment($order, $courier, 'RX_RETURN');

    $response = $this->postJson('/api/webhooks/couriers/redx', [
        'tracking_number' => 'RX_RETURN',
        'status' => 'agent-returning',
    ]);

    $response->assertOk();

    $shipment->refresh();
    expect($shipment->status)->toBe('returned');
});

it('ignores redx paid status', function () {
    $courier = createWebhookCourier('redx');
    $order = createWebhookOrder();
    $shipment = createWebhookShipment($order, $courier, 'RX_PAID');

    $response = $this->postJson('/api/webhooks/couriers/redx', [
        'tracking_number' => 'RX_PAID',
        'status' => 'paid',
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $shipment->refresh();
    expect($shipment->status)->toBe('pending');
});

// --- Steadfast (official: consignment_id/invoice, status) ---

it('handles steadfast delivered webhook', function () {
    $courier = createWebhookCourier('steadfast');
    $order = createWebhookOrder('shipped');
    $shipment = createWebhookShipment($order, $courier, 'SF_123');

    $response = $this->postJson('/api/webhooks/couriers/steadfast', [
        'consignment_id' => 'SF_123',
        'invoice' => 'ORD_SF_123',
        'status' => 'delivered',
        'cod_amount' => 1000,
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $shipment->refresh();
    expect($shipment->status)->toBe('delivered');
});

it('handles steadfast webhook using invoice field', function () {
    $courier = createWebhookCourier('steadfast');
    $order = createWebhookOrder('shipped');
    $shipment = createWebhookShipment($order, $courier, 'SF_INV');

    $response = $this->postJson('/api/webhooks/couriers/steadfast', [
        'consignment_id' => null,
        'invoice' => 'SF_INV',
        'status' => 'delivered',
    ]);

    $response->assertOk();

    $shipment->refresh();
    expect($shipment->status)->toBe('delivered');
});

it('handles steadfast in_review webhook', function () {
    $courier = createWebhookCourier('steadfast');
    $order = createWebhookOrder('pending');
    $shipment = createWebhookShipment($order, $courier, 'SF_REVIEW');

    $response = $this->postJson('/api/webhooks/couriers/steadfast', [
        'consignment_id' => 'SF_REVIEW',
        'status' => 'in_review',
    ]);

    $response->assertOk();

    $shipment->refresh();
    expect($shipment->status)->toBe('pending');
});

// --- Paperfly ---

it('handles paperfly delivered webhook', function () {
    $courier = createWebhookCourier('paperfly');
    $order = createWebhookOrder('shipped');
    $shipment = createWebhookShipment($order, $courier, 'PF_123');

    $response = $this->postJson('/api/webhooks/couriers/paperfly', [
        'tracking_number' => 'PF_123',
        'status' => 'delivered',
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $shipment->refresh();
    expect($shipment->status)->toBe('delivered');
});

// --- eCourier (official: initiated, Picked Up, In Transit, etc.) ---

it('handles ecourier delivered webhook', function () {
    $courier = createWebhookCourier('ecourier');
    $order = createWebhookOrder('shipped');
    $shipment = createWebhookShipment($order, $courier, 'EC_123');

    $response = $this->postJson('/api/webhooks/couriers/ecourier', [
        'order_id' => 'EC_123',
        'status' => 'Delivered',
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $shipment->refresh();
    expect($shipment->status)->toBe('delivered');
});

it('handles ecourier initiated webhook', function () {
    $courier = createWebhookCourier('ecourier');
    $order = createWebhookOrder('pending');
    $shipment = createWebhookShipment($order, $courier, 'EC_INIT');

    $response = $this->postJson('/api/webhooks/couriers/ecourier', [
        'order_id' => 'EC_INIT',
        'status' => 'Initiated',
    ]);

    $response->assertOk();

    $shipment->refresh();
    expect($shipment->status)->toBe('pending');
});

it('handles ecourier on the way to delivery webhook', function () {
    $courier = createWebhookCourier('ecourier');
    $order = createWebhookOrder('pending');
    $shipment = createWebhookShipment($order, $courier, 'EC_OTD');

    $response = $this->postJson('/api/webhooks/couriers/ecourier', [
        'order_id' => 'EC_OTD',
        'status' => 'On the way to Delivery',
    ]);

    $response->assertOk();

    $shipment->refresh();
    expect($shipment->status)->toBe('out_for_delivery');
});

// --- SA Paribahan ---

it('handles sa_paribahan delivered webhook', function () {
    $courier = createWebhookCourier('sa_paribahan');
    $order = createWebhookOrder('shipped');
    $shipment = createWebhookShipment($order, $courier, 'SA_123');

    $response = $this->postJson('/api/webhooks/couriers/sa_paribahan', [
        'tracking_no' => 'SA_123',
        'status' => 'delivered',
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $shipment->refresh();
    expect($shipment->status)->toBe('delivered');
});

// --- Sundarban ---

it('handles sundarban delivered webhook', function () {
    $courier = createWebhookCourier('sundarban');
    $order = createWebhookOrder('shipped');
    $shipment = createWebhookShipment($order, $courier, 'SB_123');

    $response = $this->postJson('/api/webhooks/couriers/sundarban', [
        'consignment_no' => 'SB_123',
        'current_status' => 'delivered',
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $shipment->refresh();
    expect($shipment->status)->toBe('delivered');
});

// --- Generic edge cases ---

it('returns ignored for unknown courier code', function () {
    $response = $this->postJson('/api/webhooks/couriers/nonexistent', [
        'tracking_number' => '123',
        'status' => 'delivered',
    ]);

    $response->assertOk()->assertJson(['status' => 'ignored']);
});

it('returns not_found when shipment does not exist', function () {
    createWebhookCourier('redx');

    $response = $this->postJson('/api/webhooks/couriers/redx', [
        'tracking_number' => 'NONEXISTENT',
        'status' => 'delivered',
    ]);

    $response->assertOk()->assertJson(['status' => 'not_found']);
});

it('returns ignored when no status in payload', function () {
    $courier = createWebhookCourier('redx');
    $order = createWebhookOrder();
    createWebhookShipment($order, $courier, 'RX_NOSTATUS');

    $response = $this->postJson('/api/webhooks/couriers/redx', [
        'tracking_number' => 'RX_NOSTATUS',
    ]);

    $response->assertOk()->assertJson(['status' => 'ignored']);
});

it('returns ignored when no tracking number in payload', function () {
    createWebhookCourier('redx');

    $response = $this->postJson('/api/webhooks/couriers/redx', [
        'status' => 'delivered',
    ]);

    $response->assertOk()->assertJson(['status' => 'ignored']);
});

it('does not update shipment if status mapping returns null', function () {
    $courier = createWebhookCourier('redx');
    $order = createWebhookOrder();
    $shipment = createWebhookShipment($order, $courier, 'RX_UNKNOWN');

    $response = $this->postJson('/api/webhooks/couriers/redx', [
        'tracking_number' => 'RX_UNKNOWN',
        'status' => 'some_unknown_status',
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    $shipment->refresh();
    expect($shipment->status)->toBe('pending');
});

it('syncs order status to shipped when picked', function () {
    $courier = createWebhookCourier('paperfly');
    $order = createWebhookOrder('pending');
    $shipment = createWebhookShipment($order, $courier, 'PF_SHIP');

    $this->postJson('/api/webhooks/couriers/paperfly', [
        'tracking_number' => 'PF_SHIP',
        'status' => 'picked_up',
    ]);

    $order->refresh();
    expect($order->status)->toBe('shipped');
});

it('does not downgrade order status from delivered to shipped', function () {
    $courier = createWebhookCourier('redx');
    $order = createWebhookOrder('delivered');
    $shipment = createWebhookShipment($order, $courier, 'RX_DUP');

    $this->postJson('/api/webhooks/couriers/redx', [
        'tracking_number' => 'RX_DUP',
        'status' => 'delivery-in-progress',
    ]);

    $order->refresh();
    expect($order->status)->toBe('shipped');
});

it('rejects courier webhook with invalid secret', function () {
    $courier = createWebhookCourier('steadfast');
    $courier->update(['settings' => ['webhook_secret' => 's3cret']]);
    $order = createWebhookOrder('shipped');
    $shipment = createWebhookShipment($order, $courier, 'SF_SECRET_1');

    $response = $this->postJson('/api/webhooks/couriers/steadfast', [
        'consignment_id' => 'SF_SECRET_1',
        'status' => 'delivered',
    ], ['X-Webhook-Secret' => 'wrong']);

    $response->assertStatus(401)->assertJson(['status' => 'ignored']);

    expect($shipment->fresh()->status)->toBe('pending');
});

it('accepts courier webhook with valid secret', function () {
    $courier = createWebhookCourier('steadfast');
    $courier->update(['settings' => ['webhook_secret' => 's3cret']]);
    $order = createWebhookOrder('shipped');
    $shipment = createWebhookShipment($order, $courier, 'SF_SECRET_2');

    $response = $this->postJson('/api/webhooks/couriers/steadfast', [
        'consignment_id' => 'SF_SECRET_2',
        'status' => 'delivered',
    ], ['X-Webhook-Secret' => 's3cret']);

    $response->assertOk()->assertJson(['status' => 'processed']);

    expect($shipment->fresh()->status)->toBe('delivered');
});

it('cancels shipped order and restores stock on returned webhook', function () {
    $courier = createWebhookCourier('steadfast');
    $product = Product::factory()->create(['price' => 500, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(20)->create();

    $order = createWebhookOrder('shipped');
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'quantity' => 3,
    ]);
    $shipment = createWebhookShipment($order, $courier, 'SF_RETURN_1');

    $inventory = Inventory::where('product_id', $product->id)->first();
    $inventory->update(['quantity' => 17, 'reserved_quantity' => 0]);

    $response = $this->postJson('/api/webhooks/couriers/steadfast', [
        'consignment_id' => 'SF_RETURN_1',
        'status' => 'returned',
    ]);

    $response->assertOk()->assertJson(['status' => 'processed']);

    expect($shipment->fresh()->status)->toBe('returned');
    expect($order->fresh()->status)->toBe('cancelled');
    expect($order->fresh()->cancellation_reason)->toBe('Returned by courier.');
    expect($inventory->fresh()->quantity)->toBe(20);
});

it('leaves non-shipped order alone on returned webhook', function () {
    $courier = createWebhookCourier('steadfast');
    $order = createWebhookOrder('pending');
    $shipment = createWebhookShipment($order, $courier, 'SF_RETURN_2');

    $this->postJson('/api/webhooks/couriers/steadfast', [
        'consignment_id' => 'SF_RETURN_2',
        'status' => 'returned',
    ])->assertOk();

    expect($shipment->fresh()->status)->toBe('returned');
    expect($order->fresh()->status)->toBe('pending');
});

it('leaves order alone on failed webhook', function () {
    $courier = createWebhookCourier('steadfast');
    $order = createWebhookOrder('shipped');
    $shipment = createWebhookShipment($order, $courier, 'SF_FAIL_1');

    $this->postJson('/api/webhooks/couriers/steadfast', [
        'consignment_id' => 'SF_FAIL_1',
        'status' => 'cancelled',
    ])->assertOk();

    expect($shipment->fresh()->status)->toBe('failed');
    expect($order->fresh()->status)->toBe('shipped');
});

it('cancels shipped order via api', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity(20)->create();

    $order = Order::factory()->for($user)->create(['status' => 'shipped']);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $inventory = Inventory::where('product_id', $product->id)->first();
    $inventory->update(['quantity' => 18, 'reserved_quantity' => 0]);

    $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/cancel")
        ->assertOk();

    expect($order->fresh()->status)->toBe('cancelled');
    expect($inventory->fresh()->quantity)->toBe(20);
});
