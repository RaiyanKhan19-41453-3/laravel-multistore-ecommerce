<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Services\Couriers\CourierGatewayFactory;
use App\Services\Couriers\Gateways\ECourierGateway;
use App\Services\Couriers\Gateways\PaperflyGateway;
use App\Services\Couriers\Gateways\RedXGateway;
use App\Services\Couriers\Gateways\SAParibahanGateway;
use App\Services\Couriers\Gateways\SteadfastGateway;
use App\Services\Couriers\Gateways\SundarbanGateway;
use Illuminate\Support\Facades\Http;

function createCourierAdmin(): User
{
    ensureStaffPermissions();

    $user = User::factory()->create();
    $user->assignRole('super-admin');

    return $user;
}

function createCourierOrder(string $status = 'confirmed'): Order
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

it('lists couriers from config, read-only', function () {
    $admin = createCourierAdmin();

    $response = $this->actingAs($admin)->get('/admin/couriers');

    $response->assertOk()->assertInertia(
        fn ($page) => $page->component('admin/couriers/index')
            ->has('couriers', count(config('couriers')))
            ->where('couriers.0.code', 'pathao')
    );
});

it('has no courier write routes', function () {
    $admin = createCourierAdmin();

    // GET stays (read-only page); the write verbs are gone entirely.
    $this->actingAs($admin)->post('/admin/couriers', ['name' => 'X', 'code' => 'x'])->assertStatus(405);
    $this->actingAs($admin)->put('/admin/couriers/1', ['name' => 'X'])->assertNotFound();
    $this->actingAs($admin)->delete('/admin/couriers/1')->assertNotFound();
});

it('requires admin role to manage couriers', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin/couriers');

    $response->assertForbidden();
});

it('test connection returns failure for unconfigured courier', function () {
    $admin = createCourierAdmin();

    $response = $this->actingAs($admin)->post('/admin/couriers/pathao/test-connection');

    $response->assertJson(['success' => false]);
});

it('test connection returns 404 for unknown courier', function () {
    $admin = createCourierAdmin();

    $response = $this->actingAs($admin)->post('/admin/couriers/nopeship/test-connection');

    $response->assertNotFound();
});

it('rejects send-to-courier for an unconfigured courier', function () {
    $admin = createCourierAdmin();
    $order = createCourierOrder('confirmed');

    $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/send-to-courier", [
        'courier_code' => 'pathao',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['courier_code']);
});

it('webhook endpoint accepts pathao status updates', function () {
    $order = createCourierOrder('shipped');
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'courier_code' => 'pathao',
        'courier_order_id' => 'PATHAO-123',
        'status' => 'in_transit',
    ]);

    $response = $this->postJson('/api/webhooks/pathao', [
        'event' => 'order.delivered',
        'data' => [
            'consignment_id' => 'PATHAO-123',
            'store_id' => 12345,
        ],
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('shipments', [
        'id' => $shipment->id,
        'status' => 'delivered',
    ]);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'delivered',
    ]);
});

it('webhook ignores unknown consignment', function () {
    $response = $this->postJson('/api/webhooks/pathao', [
        'event' => 'order.delivered',
        'data' => [
            'consignment_id' => 'UNKNOWN-999',
        ],
    ]);

    $response->assertOk()->assertJson(['status' => 'not_found']);
});

it('keeps sandbox mode through the settings filter', function () {
    // settingsFor must not drop false: production (sandbox=false) would
    // otherwise silently fall back to the gateway's sandbox default.
    config()->set('couriers.pathao.settings.sandbox', false);

    expect(CourierGatewayFactory::settingsFor('pathao'))
        ->toHaveKey('sandbox', false);
});

it('builds no gateway for a disabled courier', function () {
    config()->set('couriers.steadfast.settings', ['api_key' => 'x', 'secret_key' => 'y']);
    config()->set('couriers.steadfast.enabled', false);

    expect(CourierGatewayFactory::isEnabled('steadfast'))->toBeFalse()
        ->and(CourierGatewayFactory::make('steadfast'))->toBeNull();
});

it('redx gateway shows as supports_api', function () {
    $this->assertTrue(CourierGatewayFactory::supportsApi('redx'));
});

it('redx gateway factory returns null without settings', function () {
    $gateway = CourierGatewayFactory::make('redx');

    $this->assertNull($gateway);
});

it('redx gateway factory returns instance with valid settings', function () {
    config()->set('couriers.redx.settings', ['api_token' => 'test-token', 'sandbox' => true]);

    $gateway = CourierGatewayFactory::make('redx');

    $this->assertInstanceOf(RedXGateway::class, $gateway);
});

it('redx gateway fails test connection without settings', function () {
    $admin = createCourierAdmin();

    $response = $this->actingAs($admin)->post('/admin/couriers/redx/test-connection');

    $response->assertJson(['success' => false]);
});

it('redx gateway cannot create shipment without settings', function () {
    $admin = createCourierAdmin();
    $order = createCourierOrder('confirmed');

    $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/send-to-courier", [
        'courier_code' => 'redx',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['courier_code']);
});

it('paperfly gateway shows as supports_api', function () {
    $this->assertTrue(CourierGatewayFactory::supportsApi('paperfly'));
});

it('paperfly gateway factory returns null without settings', function () {
    $gateway = CourierGatewayFactory::make('paperfly');

    $this->assertNull($gateway);
});

it('paperfly gateway factory returns instance with valid settings', function () {
    config()->set('couriers.paperfly.settings', ['merchant_id' => 'test-id', 'username' => 'user', 'password' => 'pass']);

    $gateway = CourierGatewayFactory::make('paperfly');

    $this->assertInstanceOf(PaperflyGateway::class, $gateway);
});

it('paperfly gateway throws on cancel', function () {
    config()->set('couriers.paperfly.settings', ['merchant_id' => 'test-id', 'username' => 'user', 'password' => 'pass']);

    $gateway = CourierGatewayFactory::make('paperfly');

    $this->expectException(RuntimeException::class);
    $gateway->cancelShipment('TEST-123');
});

it('steadfast gateway shows as supports_api', function () {
    $this->assertTrue(CourierGatewayFactory::supportsApi('steadfast'));
});

it('steadfast gateway factory returns null without settings', function () {
    $gateway = CourierGatewayFactory::make('steadfast');

    $this->assertNull($gateway);
});

it('steadfast gateway factory returns instance with valid settings', function () {
    config()->set('couriers.steadfast.settings', ['api_key' => 'test-key', 'secret_key' => 'test-secret']);

    $gateway = CourierGatewayFactory::make('steadfast');

    $this->assertInstanceOf(SteadfastGateway::class, $gateway);
});

it('steadfast gateway throws on cancel', function () {
    config()->set('couriers.steadfast.settings', ['api_key' => 'test-key', 'secret_key' => 'test-secret']);

    $gateway = CourierGatewayFactory::make('steadfast');

    $this->expectException(RuntimeException::class);
    $gateway->cancelShipment('TEST-123');
});

it('ecourier gateway shows as supports_api', function () {
    $this->assertTrue(CourierGatewayFactory::supportsApi('ecourier'));
});

it('ecourier gateway factory returns null without settings', function () {
    $gateway = CourierGatewayFactory::make('ecourier');

    $this->assertNull($gateway);
});

it('ecourier gateway factory returns instance with valid settings', function () {
    config()->set('couriers.ecourier.settings', ['user_id' => 'test-user', 'api_key' => 'test-key']);

    $gateway = CourierGatewayFactory::make('ecourier');

    $this->assertInstanceOf(ECourierGateway::class, $gateway);
});

it('ecourier gateway throws on cancel', function () {
    config()->set('couriers.ecourier.settings', ['user_id' => 'test-user', 'api_key' => 'test-key']);

    $gateway = CourierGatewayFactory::make('ecourier');

    $this->expectException(RuntimeException::class);
    $gateway->cancelShipment('TEST-123');
});

it('sa_paribahan gateway shows as supports_api', function () {
    $this->assertTrue(CourierGatewayFactory::supportsApi('sa_paribahan'));
});

it('sa_paribahan gateway factory returns null without settings', function () {
    $gateway = CourierGatewayFactory::make('sa_paribahan');

    $this->assertNull($gateway);
});

it('sa_paribahan gateway factory returns instance with valid settings', function () {
    config()->set('couriers.sa_paribahan.settings', ['api_key' => 'test-token', 'booking_branch' => 'Dhaka']);

    $gateway = CourierGatewayFactory::make('sa_paribahan');

    $this->assertInstanceOf(SAParibahanGateway::class, $gateway);
});

it('sa_paribahan gateway throws on cancel', function () {
    config()->set('couriers.sa_paribahan.settings', ['api_key' => 'test-token', 'booking_branch' => 'Dhaka']);

    $gateway = CourierGatewayFactory::make('sa_paribahan');

    $this->expectException(RuntimeException::class);
    $gateway->cancelShipment('TEST-123');
});

it('sundarban gateway shows as supports_api', function () {
    $this->assertTrue(CourierGatewayFactory::supportsApi('sundarban'));
});

it('sundarban gateway factory returns null without settings', function () {
    $gateway = CourierGatewayFactory::make('sundarban');

    $this->assertNull($gateway);
});

it('sundarban gateway factory returns instance with valid settings', function () {
    config()->set('couriers.sundarban.settings', ['api_key' => 'test-token', 'booking_user_id' => 'test-user']);

    $gateway = CourierGatewayFactory::make('sundarban');

    $this->assertInstanceOf(SundarbanGateway::class, $gateway);
});

it('sundarban gateway throws on cancel', function () {
    config()->set('couriers.sundarban.settings', ['api_key' => 'test-token', 'booking_user_id' => 'test-user']);

    $gateway = CourierGatewayFactory::make('sundarban');

    $this->expectException(RuntimeException::class);
    $gateway->cancelShipment('TEST-123');
});

it('anchors manual shipments to the order store, not the resolved store', function () {
    $admin = createCourierAdmin();
    Store::factory()->create(['slug' => 'shp-a']);
    $storeB = Store::factory()->create(['slug' => 'shp-b']);

    $order = createCourierOrder();
    $order->update(['store_id' => $storeB->id]);

    // Platform view (no selection): resolved store falls back to the
    // default, but the shipment belongs to the order's store.
    $this->actingAs($admin)->post("/admin/orders/{$order->id}/shipments", [
        'courier_code' => 'pathao',
        'tracking_number' => 'TRACK-B-1',
    ])->assertRedirect();

    $this->assertDatabaseHas('shipments', [
        'tracking_number' => 'TRACK-B-1',
        'store_id' => $storeB->id,
    ]);
});

it('refuses to send an unpaid pending order to the courier', function () {
    Http::fake(['portal.packzy.com/*' => Http::response([
        'status' => 200,
        'consignment' => ['consignment_id' => 'SF-1', 'tracking_code' => 'SF-1'],
    ], 200)]);

    $admin = createCourierAdmin();
    config()->set('couriers.steadfast.settings', ['api_key' => 'x', 'secret_key' => 'y']);
    $order = createCourierOrder('pending');

    // Gateway would succeed, but unpaid goods must never ship: the state
    // machine rejects the jump and the admin sees the error.
    $this->actingAs($admin)->post("/admin/orders/{$order->id}/send-to-courier", [
        'courier_code' => 'steadfast',
    ])->assertSessionHasErrors(['courier_code']);

    expect($order->fresh()->status)->toBe('pending');
});
