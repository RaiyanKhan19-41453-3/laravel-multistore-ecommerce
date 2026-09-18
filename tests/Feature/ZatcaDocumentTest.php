<?php

use App\Jobs\SubmitZatcaDocument;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Zatca\UblInvoiceBuilder;
use App\Services\Zatca\ZatcaDocumentService;
use App\Services\Zatca\ZatcaSigner;
use Illuminate\Queue\Middleware\WithoutOverlapping;

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

function zatcaAmounts(string $xml): array
{
    preg_match('/<cbc:PayableAmount currencyID="SAR">([\d.]+)<\//', $xml, $payable);
    preg_match('/<cac:TaxTotal>.*?<cbc:TaxAmount currencyID="SAR">([\d.]+)<\//s', $xml, $tax);
    preg_match_all('/<cac:InvoiceLine>(.*?)<\/cac:InvoiceLine>/s', $xml, $blocks);

    $lines = [];
    $rates = [];
    $names = [];

    foreach ($blocks[1] as $block) {
        preg_match('/<cbc:LineExtensionAmount currencyID="SAR">([\d.]+)<\//', $block, $amount);
        preg_match('/<cbc:Percent>([\d.]+)<\//', $block, $rate);
        preg_match('/<cbc:Name>(.*?)<\/cbc:Name>/', $block, $name);
        $lines[] = $amount[1];
        $rates[] = $rate[1];
        $names[] = html_entity_decode($name[1]);
    }

    return [
        'payable' => $payable[1] ?? null,
        'tax' => $tax[1] ?? null,
        'lines' => $lines,
        'rates' => $rates,
        'names' => $names,
    ];
}

function zatcaDiscountedOrder(): Order
{
    config([
        'zatca.enabled' => true,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
    ]);

    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'status' => 'confirmed',
        'subtotal' => 1000,
        'discount_total' => 100,
        'shipping_cost' => 60,
        'tax_amount' => 135,
        'total' => 1095,
        'shipping_name' => 'Buyer Name',
        'shipping_phone' => '0512345678',
        'shipping_address' => 'Olaya St 5',
        'shipping_city' => 'Riyadh',
        'shipping_country' => 'SA',
    ]);
    OrderItem::factory()->for($order)->create([
        'name' => 'Widget A',
        'sku' => 'WDG-A',
        'unit_price' => 300,
        'quantity' => 2,
        'subtotal' => 600,
        'total' => 600,
    ]);
    OrderItem::factory()->for($order)->create([
        'name' => 'Widget B',
        'sku' => 'WDG-B',
        'unit_price' => 400,
        'quantity' => 1,
        'subtotal' => 400,
        'total' => 400,
    ]);

    return $order->fresh();
}

it('invoices the discounted total including delivery, reconciling with the order', function () {
    $doc = app(ZatcaDocumentService::class)->buildForOrder(zatcaDiscountedOrder(), 'simplified');
    $amounts = zatcaAmounts($doc->xml);

    // Lines: 540 + 360 discounted goods plus the 60 delivery line.
    expect($amounts['names'])->toBe(['Widget A', 'Widget B', 'Delivery']);
    expect(array_map('floatval', $amounts['lines']))->toBe([540.0, 360.0, 60.0]);
    expect($amounts['tax'])->toBe('135.00');
    // Payable matches exactly what the customer was charged (and the QR).
    expect($amounts['payable'])->toBe('1095.00');
});

it('leaves exempt lines untaxed instead of spreading VAT onto them', function () {
    config([
        'zatca.enabled' => true,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
        'zatca.exempt_category_slugs' => ['meds'],
    ]);

    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'status' => 'confirmed',
        'subtotal' => 1000,
        'discount_total' => 0,
        'shipping_cost' => 0,
        'tax_amount' => 75,
        'total' => 1075,
        'shipping_name' => 'Buyer Name',
        'shipping_phone' => '0512345678',
        'shipping_address' => 'Olaya St 5',
        'shipping_city' => 'Riyadh',
        'shipping_country' => 'SA',
    ]);

    $medicine = Product::factory()->create(['price' => 500, 'is_active' => true]);
    $category = Category::create(['name' => 'Meds', 'slug' => 'meds', 'is_active' => true]);
    $medicine->categories()->attach($category);
    OrderItem::factory()->for($order)->create([
        'product_id' => $medicine->id,
        'name' => 'Medicine',
        'sku' => 'MED-1',
        'unit_price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'total' => 500,
    ]);
    OrderItem::factory()->for($order)->create([
        'name' => 'Widget',
        'sku' => 'WDG-1',
        'unit_price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'total' => 500,
    ]);

    $doc = app(ZatcaDocumentService::class)->buildForOrder($order->fresh(), 'simplified');
    $amounts = zatcaAmounts($doc->xml);

    expect($amounts['names'])->toBe(['Medicine', 'Widget']);
    expect($amounts['rates'])->toBe(['0.00', '15.00']);
    expect($amounts['tax'])->toBe('75.00');
    expect($amounts['payable'])->toBe('1075.00');
});

it('splits taxable and exempt bases into separate tax subtotals', function () {
    config([
        'zatca.enabled' => true,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
        'zatca.exempt_category_slugs' => ['meds'],
    ]);

    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'status' => 'confirmed',
        'subtotal' => 1000,
        'discount_total' => 0,
        'shipping_cost' => 0,
        'tax_amount' => 75,
        'total' => 1075,
        'shipping_name' => 'Buyer Name',
        'shipping_phone' => '0512345678',
        'shipping_address' => 'Olaya St 5',
        'shipping_city' => 'Riyadh',
        'shipping_country' => 'SA',
    ]);

    $medicine = Product::factory()->create(['price' => 500, 'is_active' => true]);
    $category = Category::create(['name' => 'Meds', 'slug' => 'meds', 'is_active' => true]);
    $medicine->categories()->attach($category);
    OrderItem::factory()->for($order)->create([
        'product_id' => $medicine->id,
        'name' => 'Medicine',
        'sku' => 'MED-1',
        'unit_price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'total' => 500,
    ]);
    OrderItem::factory()->for($order)->create([
        'name' => 'Widget',
        'sku' => 'WDG-1',
        'unit_price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'total' => 500,
    ]);

    $xml = app(ZatcaDocumentService::class)->buildForOrder($order->fresh(), 'simplified')->xml;

    // Two subtotals (S 500 + E 500), never one S lumped at 1000.
    expect(substr_count($xml, '<cac:TaxSubtotal>'))->toBe(2);
    expect(substr_count($xml, '<cbc:TaxableAmount currencyID="SAR">500.00</cbc:TaxableAmount>'))->toBe(2);
    expect($xml)->not->toContain('<cbc:TaxableAmount currencyID="SAR">1000.00</cbc:TaxableAmount>');
    expect($xml)->toContain('<cbc:ID>E</cbc:ID>');
    expect($xml)->toContain('<cbc:PayableAmount currencyID="SAR">1075.00</cbc:PayableAmount>');
});

it('serializes zatca submissions per device without dropping documents', function () {
    config([
        'zatca.enabled' => true,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
    ]);

    $service = app(ZatcaDocumentService::class);

    config(['zatca.device.serial' => 'EGS-A']);
    $docA1 = $service->buildForOrder(zatcaOrder(), 'simplified');
    $docA2 = $service->buildForOrder(zatcaOrder(), 'simplified');

    config(['zatca.device.serial' => 'EGS-B']);
    $docB = $service->buildForOrder(zatcaOrder(), 'simplified');

    $middlewareFor = fn (int $documentId): WithoutOverlapping => (new SubmitZatcaDocument($documentId))->middleware()[0];

    $lockA1 = $middlewareFor($docA1->id);
    $lockA2 = $middlewareFor($docA2->id);
    $lockB = $middlewareFor($docB->id);
    $lockMissing = $middlewareFor(999999);

    expect($lockA1)->toBeInstanceOf(WithoutOverlapping::class);
    expect($lockA1->key)->toBe($lockA2->key);
    expect($lockB->key)->not->toBe($lockA1->key);
    expect($lockMissing->key)->toContain('default');
});
