<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Zatca\UblInvoiceBuilder;
use App\Services\Zatca\ZatcaDocumentService;
use App\Services\Zatca\ZatcaSigner;

function zatcaSeller(): array
{
    return [
        'name_ar' => 'شركة المثال',
        'name_en' => 'Example Co',
        'vat_number' => '300012345600003',
        'cr_number' => '1010010001',
        'street' => 'King Fahd Rd',
        'city' => 'Riyadh',
        'postal_code' => '12213',
        'country' => 'SA',
    ];
}

function zatcaOrder(): Order
{
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'status' => 'confirmed',
        'subtotal' => 1000,
        'tax_amount' => 150,
        'total' => 1150,
        'shipping_name' => 'Buyer Name',
        'shipping_phone' => '0512345678',
        'shipping_address' => 'Olaya St 5',
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

function zatcaLines(): array
{
    return [[
        'sku' => 'WDG-1',
        'name' => 'Widget',
        'quantity' => 1,
        'unit_price' => 1000,
        'line_total' => 1000,
        'vat_rate' => 15.0,
        'vat_amount' => 150,
    ]];
}

it('builds simplified ubl with required nodes', function () {
    $order = zatcaOrder();

    $xml = (new UblInvoiceBuilder)->buildSimplified(
        $order, zatcaSeller(), zatcaLines(),
        '11111111-2222-3333-4444-555555555555', 7,
        base64_encode('prev'), new DateTimeImmutable('2026-01-05T10:00:00+00:00')
    );

    expect($xml)
        ->toContain('<cbc:ProfileID>reporting:1.0</cbc:ProfileID>')
        ->toContain('<cbc:UUID>11111111-2222-3333-4444-555555555555</cbc:UUID>')
        ->toContain('<cbc:InvoiceCounterValue>7</cbc:InvoiceCounterValue>')
        ->toContain('<cbc:ID>'.$order->order_number.'</cbc:ID>')
        ->toContain('<cbc:PayableAmount currencyID="SAR">1150.00</cbc:PayableAmount>')
        ->toContain('<cbc:TaxAmount currencyID="SAR">150.00</cbc:TaxAmount>')
        ->toContain('شركة المثال')
        ->toContain('300012345600003');

    $doc = new DOMDocument;
    expect(@$doc->loadXML($xml))->toBeTrue();
});

it('builds standard ubl with buyer party', function () {
    $order = zatcaOrder();

    $xml = (new UblInvoiceBuilder)->buildStandard(
        $order, zatcaSeller(),
        ['name' => 'Buyer LLC', 'vat_number' => '300098765400003', 'street' => 'Tahlia St', 'city' => 'Jeddah', 'country' => 'SA'],
        zatcaLines(),
        (string) Str::uuid(), 1, base64_encode('genesis'), now()
    );

    expect($xml)
        ->toContain('<cbc:ProfileID>clearance:1.0</cbc:ProfileID>')
        ->toContain('<cac:AccountingCustomerParty>')
        ->toContain('300098765400003');
});

it('issues gapless invoice counters per device', function () {
    $service = app(ZatcaDocumentService::class);

    expect($service->nextIcv('TEST-EGS-1'))->toBe(1);
    expect($service->nextIcv('TEST-EGS-1'))->toBe(2);
    expect($service->nextIcv('TEST-EGS-2'))->toBe(1);
});

it('chains document hashes with genesis head', function () {
    config([
        'zatca.enabled' => true,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
    ]);

    $service = app(ZatcaDocumentService::class);

    expect($service->previousHash())->toBe(ZatcaDocumentService::GENESIS_HASH);

    $order = zatcaOrder();
    $first = $service->buildForOrder($order, 'simplified');

    expect($first->previous_invoice_hash)->toBe(ZatcaDocumentService::GENESIS_HASH);
    expect($first->icv)->toBeGreaterThanOrEqual(1);

    [$privatePem, $certPem] = ecKeypair();
    $signed = $service->signDocument($first, $privatePem, $certPem);

    expect($signed->status)->toBe('signed');
    expect($signed->invoice_hash)->not->toBeEmpty();

    $order2 = zatcaOrder();
    $second = $service->buildForOrder($order2, 'simplified');

    expect($second->previous_invoice_hash)->toBe($signed->invoice_hash);
});

it('signs and verifies with secp256k1 round trip', function () {
    [$privatePem, $certPem] = ecKeypair();
    $signer = new ZatcaSigner;

    $hash = $signer->invoiceHash('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"><cbc:ID xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">1</cbc:ID></Invoice>');
    $signature = $signer->sign(base64_decode($hash), $privatePem);

    $pubKey = openssl_pkey_get_public(openssl_x509_read($certPem));

    expect($signer->verify(base64_decode($hash), $signature, $pubKey))->toBeTrue();
    expect($signer->verify('tampered', $signature, $pubKey))->toBeFalse();
    expect($signer->publicKeyDer($certPem))->not->toBeEmpty();
});

it('upgrades qr payload with authority stamp as ninth tag', function () {
    config([
        'zatca.enabled' => true,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
    ]);

    $service = app(ZatcaDocumentService::class);
    $order = zatcaOrder();
    $doc = $service->buildForOrder($order, 'simplified');

    [$privatePem, $certPem] = ecKeypair();
    $signed = $service->signDocument($doc, $privatePem, $certPem);

    $tags = decodeTlv(base64_decode($signed->qr_payload));
    expect(array_keys($tags))->toBe([1, 2, 3, 4, 5, 6, 7, 8]);

    $stamped = $service->applyAuthorityStamp($signed, base64_encode('authority-stamp'));

    $tags = decodeTlv(base64_decode($stamped->qr_payload));
    expect(array_keys($tags))->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    expect($tags[9])->toBe(base64_encode('authority-stamp'));
});

it('refuses documents without seller profile', function () {
    config(['zatca.enabled' => true, 'zatca.seller.name_ar' => '', 'zatca.seller.vat_number' => 'bad']);

    expect(fn () => app(ZatcaDocumentService::class)->buildForOrder(zatcaOrder(), 'simplified'))
        ->toThrow(InvalidArgumentException::class);
});

it('emits quantity without currency attribute', function () {
    $order = zatcaOrder();

    $xml = (new UblInvoiceBuilder)->buildSimplified(
        $order, zatcaSeller(), zatcaLines(),
        (string) Str::uuid(), 1, base64_encode('prev'), now()
    );

    expect($xml)->toContain('<cbc:InvoicedQuantity unitCode="PCE">1.00</cbc:InvoicedQuantity>');
    expect($xml)->not->toContain('<cbc:InvoicedQuantity currencyID');
});

it('builds credit and debit notes with correct type codes', function () {
    $order = zatcaOrder();
    $builder = new UblInvoiceBuilder;

    $credit = $builder->buildCreditNote(
        $order, zatcaSeller(), null, zatcaLines(),
        (string) Str::uuid(), 2, base64_encode('prev'), now()
    );
    $debit = $builder->buildDebitNote(
        $order, zatcaSeller(), null, zatcaLines(),
        (string) Str::uuid(), 3, base64_encode('prev'), now()
    );

    expect($credit)->toContain('<cbc:InvoiceTypeCode name="0200000">381</cbc:InvoiceTypeCode>');
    expect($debit)->toContain('<cbc:InvoiceTypeCode name="0200000">383</cbc:InvoiceTypeCode>');

    foreach ([$credit, $debit] as $xml) {
        $doc = new DOMDocument;
        expect(@$doc->loadXML($xml))->toBeTrue();
    }
});

it('scopes hash chains per device', function () {
    config([
        'zatca.enabled' => true,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
    ]);

    $service = app(ZatcaDocumentService::class);
    [$privatePem, $certPem] = ecKeypair();

    config(['zatca.device.serial' => 'EGS-A']);
    $docA = $service->buildForOrder(zatcaOrder(), 'simplified');
    $signedA = $service->signDocument($docA, $privatePem, $certPem);

    expect($docA->device_serial)->toBe('EGS-A');
    expect($docA->previous_invoice_hash)->toBe(ZatcaDocumentService::GENESIS_HASH);

    config(['zatca.device.serial' => 'EGS-B']);
    $docB = $service->buildForOrder(zatcaOrder(), 'simplified');

    expect($docB->device_serial)->toBe('EGS-B');
    expect($docB->previous_invoice_hash)->toBe(ZatcaDocumentService::GENESIS_HASH);
    expect($service->previousHash('EGS-A'))->toBe($signedA->invoice_hash);
});
