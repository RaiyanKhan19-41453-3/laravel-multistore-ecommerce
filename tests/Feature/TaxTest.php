<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\TaxService;

it('stays disabled by default with zero tax', function () {
    config(['zatca.enabled' => false]);

    $service = new TaxService;

    expect($service->enabled())->toBeFalse();
    expect($service->vatFor(1000))->toBe(0.0);
});

it('computes 15 percent vat when enabled', function () {
    config(['zatca.enabled' => true, 'zatca.vat_rate' => 15.0]);

    expect((new TaxService)->vatFor(1000))->toBe(150.0);
    expect((new TaxService)->vatFor(99.99))->toBe(15.0);
});

it('validates 15-digit vat numbers', function () {
    $service = new TaxService;

    expect($service->isValidVatNumber('300012345600003'))->toBeTrue();
    expect($service->isValidVatNumber(' 300012345600003 '))->toBeTrue();
    expect($service->isValidVatNumber('12345'))->toBeFalse();
    expect($service->isValidVatNumber('30001234560000A'))->toBeFalse();
    expect($service->isValidVatNumber(null))->toBeFalse();
});

it('requires arabic name and vat number for seller profile', function () {
    config(['zatca.seller.name_ar' => 'شركة المثال', 'zatca.seller.vat_number' => '300012345600003']);

    expect((new TaxService)->hasValidSellerProfile())->toBeTrue();

    config(['zatca.seller.vat_number' => 'bad']);

    expect((new TaxService)->hasValidSellerProfile())->toBeFalse();
});

it('exempts configured categories case-insensitively', function () {
    config([
        'zatca.enabled' => true,
        'zatca.zero_rated_category_slugs' => ['Medicine'],
        'zatca.exempt_category_slugs' => [],
    ]);

    $category = Category::create(['name' => 'Medicine', 'slug' => 'medicine']);
    $product = Product::factory()->create();
    $product->categories()->attach($category);

    expect((new TaxService)->isExempt($product->fresh()))->toBeTrue();
    expect((new TaxService)->isExempt(Product::factory()->create()))->toBeFalse();
});

it('leaves tax at zero for existing checkout flow', function () {
    config(['zatca.enabled' => false]);

    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 2);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'order' => [
                'status' => 'confirmed',
                'total' => '1060.00',
            ],
        ],
    ]);
});

it('adds vat to checkout total when enabled', function () {
    config(['zatca.enabled' => true, 'zatca.vat_rate' => 15.0]);

    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 2);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    // subtotal 1000 + shipping 60 = 1060 base, 15% VAT = 159, total 1219
    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'order' => [
                'status' => 'confirmed',
                'total' => '1219.00',
            ],
        ],
    ]);

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'tax_amount' => '159.00',
        'total' => '1219.00',
    ]);
});

it('includes zatca qr in order lookup when enabled with valid profile', function () {
    config([
        'zatca.enabled' => true,
        'zatca.vat_rate' => 15.0,
        'zatca.seller.name_ar' => 'شركة المثال',
        'zatca.seller.vat_number' => '300012345600003',
    ]);

    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user))->assertOk();

    $order = Order::where('user_id', $user->id)->first();

    $response = $this->postJson('/api/orders/lookup', [
        'phone' => '01712345678',
        'order_number' => $order->order_number,
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'zatca' => [
                'seller_name_ar' => 'شركة المثال',
                'vat_number' => '300012345600003',
            ],
        ],
    ]);

    expect($response->json('data.zatca.qr_svg'))->toContain('<svg');
});

it('omits zatca qr when disabled or profile incomplete', function () {
    config(['zatca.enabled' => false]);

    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 1);

    $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user))->assertOk();

    $order = Order::where('user_id', $user->id)->first();

    $response = $this->postJson('/api/orders/lookup', [
        'phone' => '01712345678',
        'order_number' => $order->order_number,
    ]);

    $response->assertOk();
    expect($response->json('data'))->not->toHaveKey('zatca');
});
