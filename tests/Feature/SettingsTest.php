<?php

use App\Models\Product;
use App\Services\CurrencyService;
use App\Services\SettingsService;
use App\Services\TaxService;

it('persists settings and invalidates cache', function () {
    $settings = app(SettingsService::class);

    expect($settings->get('store.currency'))->toBeNull();

    $settings->set('store.currency', 'SAR', 'store');

    expect($settings->get('store.currency'))->toBe('SAR');
    $this->assertDatabaseHas('settings', ['key' => 'store.currency', 'value' => 'SAR']);
});

it('applies BD and SA presets', function () {
    $settings = app(SettingsService::class);

    $settings->applyPreset('BD');

    expect($settings->get('store.currency'))->toBe('BDT')
        ->and($settings->get('tax.mode'))->toBe('off');

    $settings->applyPreset('SA');

    expect($settings->get('store.currency'))->toBe('SAR')
        ->and($settings->get('store.locale'))->toBe('ar')
        ->and($settings->get('tax.mode'))->toBe('vat')
        ->and($settings->get('tax.rate'))->toBe('15');
});

it('rejects unknown presets', function () {
    expect(fn () => app(SettingsService::class)->applyPreset('XX'))
        ->toThrow(InvalidArgumentException::class);
});

it('enables generic vat tax from settings without zatca', function () {
    config(['zatca.enabled' => false, 'tax.mode' => 'off', 'tax.rate' => 0]);

    app(SettingsService::class)->setMany(['tax.mode' => 'vat', 'tax.rate' => '10'], 'tax');

    $tax = app(TaxService::class);

    expect($tax->mode())->toBe('vat')
        ->and($tax->enabled())->toBeTrue()
        ->and($tax->vatFor(1000))->toBe(100.0);
});

it('keeps vat on when zatca is enabled even if generic mode is off', function () {
    config(['zatca.enabled' => true, 'zatca.vat_rate' => 15.0, 'tax.mode' => 'off', 'tax.rate' => 0]);

    $tax = app(TaxService::class);

    expect($tax->enabled())->toBeTrue()
        ->and($tax->vatFor(1000))->toBe(150.0);
});

it('adds vat to checkout total from store settings', function () {
    config(['zatca.enabled' => false, 'tax.mode' => 'off', 'tax.rate' => 0]);
    app(SettingsService::class)->setMany(['tax.mode' => 'vat', 'tax.rate' => '15'], 'tax');

    $user = createUser();
    $product = createProduct(500, 20);
    createCartWithItem($user, $product, 2);

    $response = $this->postJson('/api/checkout', [
        ...shippingData(),
        'payment_method' => 'cod',
    ], authHeaders($user));

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => ['order' => ['total' => '1219.00']],
    ]);

    $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'tax_amount' => '159.00']);
});

it('formats BDT and SAR currencies and falls back safely', function () {
    $currency = app(CurrencyService::class);

    expect($currency->format(1500, 'BDT'))->toContain('৳')
        ->and($currency->format(1500, 'SAR'))->toContain('ر.س')
        ->and($currency->format(10, 'XX'))->toContain('৳');
});

it('reads currency code from settings', function () {
    app(SettingsService::class)->set('store.currency', 'SAR', 'store');

    expect(app(CurrencyService::class)->code())->toBe('SAR');
});

it('returns arabic catalog names when locale is ar', function () {
    $product = Product::factory()->create(['name' => 'Sneakers', 'name_ar' => 'حذاء رياضي']);

    app()->setLocale('en');
    expect($product->displayName())->toBe('Sneakers');

    app()->setLocale('ar');
    expect($product->displayName())->toBe('حذاء رياضي');

    $response = $this->getJson("/api/products/{$product->slug}");

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => ['name' => 'حذاء رياضي', 'name_ar' => 'حذاء رياضي'],
    ]);
});

it('guests cannot access admin settings', function () {
    $this->get('/admin/settings')->assertRedirect('/admin/login');
});

it('non-admin users are forbidden from admin settings', function () {
    $this->actingAs(createUser());

    $this->get('/admin/settings')->assertForbidden();
});

it('super-admin can view and update settings', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->get('/admin/settings')->assertOk();

    // Inertia submits JSON, where dotted keys survive (PHP mangles dots
    // in form-encoded bodies to underscores).
    $this->actingAs($admin)->putJson('/admin/settings', [
        'store' => ['currency' => 'SAR', 'locale' => 'ar'],
        'tax' => ['mode' => 'vat', 'rate' => 15],
    ])->assertRedirect('/admin/settings');

    expect(app(SettingsService::class)->get('store.currency'))->toBe('SAR');
});

it('super-admin can apply a country preset', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->post('/admin/settings/preset', [
        'country' => 'SA',
    ])->assertRedirect('/admin/settings');

    $settings = app(SettingsService::class);

    expect($settings->get('store.currency'))->toBe('SAR')
        ->and($settings->get('tax.mode'))->toBe('vat');
});

it('validates settings input', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->putJson('/admin/settings', [
        'store' => ['currency' => 'NOPE'],
        'tax' => ['rate' => 500],
    ])->assertJsonValidationErrors(['store.currency', 'tax.rate']);
});

it('ignores an invalid store timezone instead of breaking requests', function () {
    $admin = createAdmin();
    app(SettingsService::class)->set('store.timezone', 'Not/AZone', 'store');

    $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    expect(date_default_timezone_get())->not->toBe('Not/AZone');
});
