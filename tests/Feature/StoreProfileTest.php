<?php

use App\Models\Product;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows the store profile page', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->get('/admin/store-profile')->assertOk();
});

it('guests cannot open the store profile page', function () {
    $this->get('/admin/store-profile')->assertRedirect('/admin/login');
});

it('staff without the settings permission cannot open it', function () {
    $user = createStaffUser('support');

    $this->actingAs($user)->get('/admin/store-profile')->assertForbidden();
});

it('saves store profile contact fields', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->put('/admin/store-profile', [
        'name' => 'Demo Shop',
        'tagline' => 'Fresh daily',
        'email' => 'hello@example.com',
        'phone' => '01700000000',
        'address' => '12 Green Road',
        'city' => 'Dhaka',
    ])->assertRedirect('/admin/store-profile');

    $settings = app(SettingsService::class);

    expect($settings->get('store.name'))->toBe('Demo Shop')
        ->and($settings->get('store.tagline'))->toBe('Fresh daily')
        ->and($settings->get('store.email'))->toBe('hello@example.com')
        ->and($settings->get('store.phone'))->toBe('01700000000')
        ->and($settings->get('store.address'))->toBe('12 Green Road')
        ->and($settings->get('store.city'))->toBe('Dhaka');
});

it('saves text fields when the page submits an explicit null logo', function () {
    $admin = createAdmin();

    // Mirrors the page payload: logo_file is always present, null unless picked.
    $this->actingAs($admin)->putJson('/admin/store-profile', [
        'name' => 'Null Logo Shop',
        'logo_file' => null,
    ])->assertRedirect('/admin/store-profile');

    expect(app(SettingsService::class)->get('store.name'))->toBe('Null Logo Shop');
    expect(app(SettingsService::class)->get('store.logo'))->toBeNull();
});

it('uploads and removes the store logo', function () {
    Storage::fake('public');
    $admin = createAdmin();

    $this->actingAs($admin)->post('/admin/store-profile', [
        '_method' => 'PUT',
        'logo_file' => UploadedFile::fake()->image('logo.png', 200, 200)->size(100),
    ])->assertRedirect('/admin/store-profile');

    $logo = app(SettingsService::class)->get('store.logo');

    expect($logo)->toStartWith('/storage/logos/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $logo));

    $this->actingAs($admin)->post('/admin/store-profile', [
        '_method' => 'PUT',
        'remove_logo' => true,
    ])->assertRedirect('/admin/store-profile');

    expect(app(SettingsService::class)->get('store.logo'))->toBeNull();
    Storage::disk('public')->assertMissing(str_replace('/storage/', '', $logo));
});

it('rejects a non-image store logo', function () {
    Storage::fake('public');
    $admin = createAdmin();

    $this->actingAs($admin)->post('/admin/store-profile', [
        '_method' => 'PUT',
        'logo_file' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors(['logo_file']);

    expect(app(SettingsService::class)->get('store.logo'))->toBeNull();
});

it('saves search and social settings', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->put('/admin/store-profile', [
        'meta_title' => 'Demo Shop — quality online',
        'meta_description' => 'Shop quality products with fast delivery.',
        'theme_color' => '#123abc',
    ])->assertRedirect('/admin/store-profile');

    $settings = app(SettingsService::class);

    expect($settings->get('store.meta_title'))->toBe('Demo Shop — quality online')
        ->and($settings->get('store.meta_description'))->toBe('Shop quality products with fast delivery.')
        ->and($settings->get('store.theme_color'))->toBe('#123abc');
});

it('rejects an invalid theme color', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->putJson('/admin/store-profile', [
        'theme_color' => 'not-a-color',
    ])->assertJsonValidationErrors(['theme_color']);
});

it('uploads and removes the favicon and share image', function () {
    Storage::fake('public');
    $admin = createAdmin();

    $this->actingAs($admin)->post('/admin/store-profile', [
        '_method' => 'PUT',
        'favicon_file' => UploadedFile::fake()->image('favicon.png', 64, 64)->size(20),
        'og_image_file' => UploadedFile::fake()->image('share.jpg', 1200, 630)->size(200),
    ])->assertRedirect('/admin/store-profile');

    $settings = app(SettingsService::class);
    $favicon = $settings->get('store.favicon');
    $ogImage = $settings->get('store.og_image');

    expect($favicon)->toStartWith('/storage/favicons/');
    expect($ogImage)->toStartWith('/storage/social/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $favicon));
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $ogImage));

    $this->actingAs($admin)->post('/admin/store-profile', [
        '_method' => 'PUT',
        'remove_favicon' => true,
        'remove_og_image' => true,
    ])->assertRedirect('/admin/store-profile');

    expect(app(SettingsService::class)->get('store.favicon'))->toBeNull();
    expect(app(SettingsService::class)->get('store.og_image'))->toBeNull();
    Storage::disk('public')->assertMissing(str_replace('/storage/', '', $favicon));
    Storage::disk('public')->assertMissing(str_replace('/storage/', '', $ogImage));
});

it('renders saved meta defaults in the storefront head', function () {
    $settings = app(SettingsService::class);
    $settings->set('store.meta_title', 'Demo Shop', 'store');
    $settings->set('store.meta_description', 'Best shop ever.', 'store');
    $settings->set('store.theme_color', '#123abc', 'store');
    $settings->set('store.favicon', '/storage/favicons/demo.png', 'store');

    $this->get('/')
        ->assertOk()
        ->assertSee('<link rel="icon" href="'.config('app.url').'/storage/favicons/demo.png">', false)
        ->assertSee('<meta name="theme-color" content="#123abc">', false)
        ->assertSee('<meta name="description" content="Best shop ever.">', false)
        ->assertSee('<meta property="og:site_name" content="Demo Shop">', false);
});

it('saves analytics and pixel IDs', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->put('/admin/store-profile', [
        'google_tag_id' => 'GTM-ABC123',
        'google_site_verification' => 'dBw5Cv9xY8-abc_DEF123',
        'meta_pixel_id' => '123456789012345',
    ])->assertRedirect('/admin/store-profile');

    $settings = app(SettingsService::class);

    expect($settings->get('marketing.google_tag_id'))->toBe('GTM-ABC123')
        ->and($settings->get('marketing.google_site_verification'))->toBe('dBw5Cv9xY8-abc_DEF123')
        ->and($settings->get('marketing.meta_pixel_id'))->toBe('123456789012345');
});

it('rejects malformed analytics IDs', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->putJson('/admin/store-profile', [
        'google_tag_id' => 'GTM-ABC123";alert(1)',
        'google_site_verification' => 'tok"en',
        'meta_pixel_id' => 'abc',
    ])->assertJsonValidationErrors(['google_tag_id', 'google_site_verification', 'meta_pixel_id']);
});

it('renders analytics snippets only when configured', function () {
    $settings = app(SettingsService::class);
    $settings->set('marketing.google_tag_id', 'G-ABC123XYZ', 'marketing');
    $settings->set('marketing.google_site_verification', 'verify-token-1', 'marketing');
    $settings->set('marketing.meta_pixel_id', '987654321', 'marketing');

    $this->get('/')
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ', false)
        ->assertSee('<meta name="google-site-verification" content="verify-token-1">', false)
        ->assertSee("fbq('init','987654321')", false);
});

it('renders tag manager container when a GTM id is configured', function () {
    app(SettingsService::class)->set('marketing.google_tag_id', 'GTM-XYZ789', 'marketing');

    $this->get('/')
        ->assertOk()
        ->assertSee("'GTM-XYZ789'", false)
        ->assertSee('https://www.googletagmanager.com/ns.html?id=GTM-XYZ789', false);
});

it('omits analytics snippets when nothing is configured', function () {
    $body = $this->get('/')->assertOk()->getContent();

    expect($body)->not->toContain('googletagmanager')
        ->and($body)->not->toContain('google-site-verification')
        ->and($body)->not->toContain('fbevents.js');
});

it('lets product pages keep their own share image instead of the default', function () {
    app(SettingsService::class)->set('store.og_image', '/storage/social/default.jpg', 'store');

    $product = Product::factory()->create(['is_active' => true]);

    $body = $this->get("/products/{$product->slug}")->assertOk()->getContent();

    expect($body)->not->toContain('/storage/social/default.jpg');

    $home = $this->get('/')->assertOk()->getContent();

    expect($home)->toContain('/storage/social/default.jpg');
});
