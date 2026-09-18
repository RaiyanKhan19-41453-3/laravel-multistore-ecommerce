<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use App\Models\ZatcaDevice;
use App\Services\SettingsService;
use App\Services\TaxService;
use App\Services\Zatca\ZatcaDocumentService;

function zatcaStoreOrder(Store $store): Order
{
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'store_id' => $store->id,
        'status' => 'confirmed',
        'subtotal' => 1000,
        'tax_amount' => 150,
        'total' => 1150,
        'shipping_name' => 'Buyer',
        'shipping_phone' => '0512345678',
        'shipping_address' => 'Olaya 5',
        'shipping_city' => 'Riyadh',
        'shipping_country' => 'SA',
    ]);
    OrderItem::factory()->for($order)->create([
        'store_id' => $store->id,
        'name' => 'Widget',
        'sku' => 'WDG-1',
        'unit_price' => 1000,
        'quantity' => 1,
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    return $order->fresh();
}

function zatcaStoreDevice(string $serial, ?Store $store = null): ZatcaDevice
{
    [$privatePem, $certPem] = ecKeypair();

    $device = ZatcaDevice::create([
        'serial' => $serial,
        'private_key' => $privatePem,
        'certificate' => $certPem,
        'csid' => 'test-binary-token',
        'csid_secret' => 'test-secret',
        'onboarded_at' => now(),
    ]);

    if ($store) {
        $device->store_id = $store->id;
        $device->save();
    }

    return $device;
}

it('merges per-store seller overrides over global config', function () {
    config([
        'zatca.seller.name_ar' => 'شركة الأساس',
        'zatca.seller.vat_number' => '300012345600003',
    ]);

    $storeA = Store::factory()->create(['slug' => 'ztx-a']);
    $storeB = Store::factory()->create(['slug' => 'ztx-b']);

    $tax = app(TaxService::class);
    $tax->sellerProfile($storeA->id); // warm, no assertions

    app(SettingsService::class)->set('zatca.seller.vat_number', '310122393500003', 'zatca', $storeA->id);

    expect($tax->sellerProfile($storeA->id)['vat_number'])->toBe('310122393500003')
        ->and($tax->sellerProfile($storeA->id)['name_ar'])->toBe('شركة الأساس')
        ->and($tax->sellerProfile($storeB->id)['vat_number'])->toBe('300012345600003');

    expect($tax->hasValidSellerProfile($storeA->id))->toBeTrue();
    expect($tax->hasValidSellerProfile(null))->toBeTrue();
});

it('keeps device chains and documents per store', function () {
    config([
        'zatca.enabled' => true,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
        'zatca.device.serial' => 'GLOBAL-EGS',
    ]);

    $storeA = Store::factory()->create(['slug' => 'ztxd-a']);
    $storeB = Store::factory()->create(['slug' => 'ztxd-b']);

    zatcaStoreDevice('EGS-A', $storeA);
    zatcaStoreDevice('EGS-B', $storeB);
    zatcaStoreDevice('GLOBAL-EGS');

    $service = app(ZatcaDocumentService::class);

    expect($service->deviceForStore($storeA->id)?->serial)->toBe('EGS-A')
        ->and($service->deviceForStore($storeB->id)?->serial)->toBe('EGS-B')
        ->and($service->deviceForStore(null)?->serial)->toBe('GLOBAL-EGS');

    $docA = $service->buildForOrder(zatcaStoreOrder($storeA), 'simplified');
    $docB = $service->buildForOrder(zatcaStoreOrder($storeB), 'simplified');

    expect($docA->device_serial)->toBe('EGS-A')
        ->and($docB->device_serial)->toBe('EGS-B')
        ->and($docA->store_id)->toBe($storeA->id)
        ->and($docB->store_id)->toBe($storeB->id)
        ->and($docA->icv)->toBe(1)
        ->and($docB->icv)->toBe(1)
        ->and($docA->previous_invoice_hash)->toBe(ZatcaDocumentService::GENESIS_HASH)
        ->and($docB->previous_invoice_hash)->toBe(ZatcaDocumentService::GENESIS_HASH);

    // Second document chains within its own store only.
    [$privatePem, $certPem] = ecKeypair();
    $signedA = $service->signDocument($docA, $privatePem, $certPem);

    $docA2 = $service->buildForOrder(zatcaStoreOrder($storeA), 'simplified');

    expect($docA2->icv)->toBe(2)
        ->and($docA2->previous_invoice_hash)->toBe($signedA->invoice_hash)
        ->and($service->previousHash('EGS-B'))->toBe(ZatcaDocumentService::GENESIS_HASH);
});

it('stamps the signing store seller identity on the qr', function () {
    config([
        'zatca.enabled' => true,
        'zatca.seller.name_ar' => 'شركة الأساس',
        'zatca.seller.vat_number' => '300012345600003',
        'zatca.device.serial' => 'QR-EGS',
    ]);

    $storeA = Store::factory()->create(['slug' => 'ztxq-a']);
    app(SettingsService::class)->set('zatca.seller.vat_number', '310122393500003', 'zatca', $storeA->id);

    $service = app(ZatcaDocumentService::class);
    $doc = $service->buildForOrder(zatcaStoreOrder($storeA), 'simplified');

    [$privatePem, $certPem] = ecKeypair();
    $signed = $service->signDocument($doc, $privatePem, $certPem);

    $tags = decodeTlv(base64_decode($signed->qr_payload));

    expect($tags[1])->toBe('شركة الأساس')
        ->and($tags[2])->toBe('310122393500003');
});

it('scopes the admin zatca listing to the selected store', function () {
    config([
        'zatca.enabled' => true,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
        'zatca.device.serial' => 'ADM-EGS',
    ]);

    $admin = createAdmin();
    $storeA = Store::factory()->create(['slug' => 'ztxc-a']);
    $storeB = Store::factory()->create(['slug' => 'ztxc-b']);

    $service = app(ZatcaDocumentService::class);
    $service->buildForOrder(zatcaStoreOrder($storeA), 'simplified');
    $service->buildForOrder(zatcaStoreOrder($storeB), 'simplified');

    $this->actingAs($admin)->get('/admin/zatca', ['X-Store-Slug' => 'ztxc-a'])
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/zatca/index')->has('documents.data', 1));
});

it('onboards a device onto a store via --store', function () {
    $store = Store::factory()->create(['slug' => 'ztxo-a']);

    $this->artisan('zatca:onboard', ['--serial' => 'STORE-EGS-1', '--store' => 'ztxo-a', '--show-csr' => true])
        ->assertSuccessful();

    $device = ZatcaDevice::find('STORE-EGS-1');

    expect($device)->not->toBeNull()
        ->and($device->store_id)->toBe($store->id)
        ->and((string) $device->csr)->not->toBeEmpty();

    $this->artisan('zatca:onboard', ['--serial' => 'STORE-EGS-2', '--store' => 'nope', '--show-csr' => true])
        ->assertFailed();
});
