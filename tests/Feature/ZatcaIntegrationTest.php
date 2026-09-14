<?php

use App\Jobs\SubmitZatcaDocument;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\ZatcaDevice;
use App\Models\ZatcaDocument;
use App\Services\Zatca\FatooraClient;
use App\Services\Zatca\ZatcaDocumentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function zatcaEnable(array $overrides = []): void
{
    config(array_merge([
        'zatca.enabled' => true,
        'zatca.vat_rate' => 15.0,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
        'zatca.device.serial' => 'TEST-EGS',
    ], $overrides));
}

function zatcaOnboardedDevice(array $overrides = []): ZatcaDevice
{
    [$privatePem, $certPem] = ecKeypair();

    return ZatcaDevice::create(array_merge([
        'serial' => 'TEST-EGS',
        'private_key' => $privatePem,
        'certificate' => $certPem,
        'csid' => 'test-binary-token',
        'csid_secret' => 'test-secret',
        'onboarded_at' => now(),
    ], $overrides));
}

function zatcaConfirmedOrder(): Order
{
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
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
        'name' => 'Widget',
        'sku' => 'WDG-1',
        'unit_price' => 1000,
        'quantity' => 1,
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    return $order->fresh();
}

function fakeFatooraAccept(string $stamp = 'c3RhbXA='): void
{
    Http::fake([
        'gw-fatoora.zatca.gov.sa/*' => Http::response([
            'validationResults' => ['status' => 'PASS', 'warningMessages' => [], 'errorMessages' => []],
            'cryptographicStamp' => $stamp,
        ], 200),
    ]);
}

it('reports simplified document and applies authority stamp', function () {
    zatcaEnable();
    zatcaOnboardedDevice();
    $order = zatcaConfirmedOrder();

    fakeFatooraAccept();

    $service = app(ZatcaDocumentService::class);
    $document = $service->buildForOrder($order, 'simplified');

    (new SubmitZatcaDocument($document->id))->handle($service, new FatooraClient);

    $document->refresh();

    expect($document->status)->toBe('reported');
    expect($document->invoice_hash)->not->toBeEmpty();
    expect($document->submit_attempts)->toBe(1);

    $tags = decodeTlv(base64_decode($document->qr_payload));
    expect(array_keys($tags))->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    expect($tags[9])->toBe('c3RhbXA=');
});

it('clears standard documents', function () {
    zatcaEnable();
    zatcaOnboardedDevice();
    $order = zatcaConfirmedOrder();

    fakeFatooraAccept();

    $service = app(ZatcaDocumentService::class);
    $document = $service->buildForOrder($order, 'standard', [
        'name' => 'Buyer LLC',
        'vat_number' => '300098765400003',
        'street' => 'Tahlia St',
        'city' => 'Jeddah',
        'country' => 'SA',
    ]);

    (new SubmitZatcaDocument($document->id))->handle($service, new FatooraClient);

    expect($document->fresh()->status)->toBe('cleared');
});

it('leaves failed submissions pending for retry', function () {
    zatcaEnable();
    zatcaOnboardedDevice();
    $order = zatcaConfirmedOrder();

    Http::fake([
        'gw-fatoora.zatca.gov.sa/*' => Http::response([
            'validationResults' => ['status' => 'FAIL', 'warningMessages' => [], 'errorMessages' => ['Invalid VAT']],
        ], 400),
    ]);

    $service = app(ZatcaDocumentService::class);
    $document = $service->buildForOrder($order, 'simplified');

    expect(fn () => (new SubmitZatcaDocument($document->id))->handle($service, new FatooraClient))
        ->toThrow(RuntimeException::class);

    expect($document->fresh()->status)->toBe('signed');
    expect($document->fresh()->submit_attempts)->toBe(1);
});

it('skips submission without onboarded device', function () {
    zatcaEnable();
    $order = zatcaConfirmedOrder();

    $service = app(ZatcaDocumentService::class);
    $document = $service->buildForOrder($order, 'simplified');

    $job = new SubmitZatcaDocument($document->id);

    try {
        $job->handle($service, new FatooraClient);
        $this->fail('Expected job failure without device.');
    } catch (Throwable) {
        expect($document->fresh()->status)->toBe('draft');
    }
});

it('queues simplified document on order confirmation when submittable', function () {
    zatcaEnable();
    zatcaOnboardedDevice();
    Queue::fake([SubmitZatcaDocument::class]);

    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user))->assertOk();

    Queue::assertPushed(SubmitZatcaDocument::class, 1);
});

it('never touches invoicing when zatca disabled', function () {
    config(['zatca.enabled' => false]);
    Queue::fake([SubmitZatcaDocument::class]);

    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user))->assertOk();

    Queue::assertNotPushed(SubmitZatcaDocument::class);
    expect(ZatcaDocument::count())->toBe(0);
});

it('onboards compliance then production csid', function () {
    Http::fake([
        'gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance' => Http::response([
            'binarySecurityToken' => 'compliance-token',
            'secret' => 'compliance-secret',
            'requestID' => 'REQ-1',
        ], 200),
        'gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/production/csids' => Http::response([
            'binarySecurityToken' => 'prod-token',
            'secret' => 'prod-secret',
            'requestID' => 'REQ-2',
        ], 200),
    ]);

    $client = new FatooraClient;

    $compliance = $client->requestComplianceCsid('CSR-DATA', '123456');
    expect($compliance['binarySecurityToken'])->toBe('compliance-token');

    $production = $client->requestProductionCsid('REQ-1', 'compliance-token', 'compliance-secret');
    expect($production['binarySecurityToken'])->toBe('prod-token');
});

it('admin views the zatca ledger', function () {
    $admin = createAdmin();
    zatcaEnable();
    zatcaOnboardedDevice();
    $order = zatcaConfirmedOrder();

    app(ZatcaDocumentService::class)->buildForOrder($order, 'simplified');

    $response = $this->actingAs($admin)->get('/admin/zatca');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->has('device')
    );
});

it('admin retries a pending document', function () {
    $admin = createAdmin();
    zatcaEnable();
    $order = zatcaConfirmedOrder();

    $document = app(ZatcaDocumentService::class)->buildForOrder($order, 'simplified');

    Queue::fake([SubmitZatcaDocument::class]);

    $this->actingAs($admin)
        ->postJson("/admin/zatca/documents/{$document->id}/retry")
        ->assertRedirect();

    Queue::assertPushed(SubmitZatcaDocument::class, 1);
});

it('admin cannot retry a finalized document', function () {
    $admin = createAdmin();
    zatcaEnable();
    $order = zatcaConfirmedOrder();

    $document = app(ZatcaDocumentService::class)->buildForOrder($order, 'simplified');
    $document->update(['status' => 'reported']);

    Queue::fake([SubmitZatcaDocument::class]);

    $this->actingAs($admin)
        ->postJson("/admin/zatca/documents/{$document->id}/retry")
        ->assertRedirect();

    Queue::assertNotPushed(SubmitZatcaDocument::class);
});
