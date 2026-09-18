<?php

use App\Models\Banner;
use App\Models\Store;
use App\Models\User;
use App\Services\HomepageService;
use App\Support\CurrentStore;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Cache::flush();
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
});

it('serves ordered active banners with safe links', function () {
    Banner::factory()->create(['title' => 'First', 'layout' => 'double', 'text_layout' => 'split', 'button_link' => '/products', 'sort_order' => 1]);
    Banner::factory()->create(['title' => 'Hidden', 'is_active' => false]);
    Banner::factory()->create(['title' => 'Second', 'layout' => 'quad', 'text_layout' => 'center', 'images' => ['/storage/banners/a.jpg'], 'sort_order' => 0]);

    $banners = app(HomepageService::class)->banners();

    expect(array_column($banners, 'title'))->toBe(['Second', 'First']);
    expect($banners[0]['layout'])->toBe('quad');
    expect($banners[0]['images'])->toBe(['/storage/banners/a.jpg']);
    expect($banners[1]['button_link'])->toBe('/products');
});

it('falls back to safe defaults for bad stored values', function () {
    $banner = Banner::factory()->create(['button_link' => 'javascript:alert(1)']);
    $banner->fill(['layout' => 'masonry', 'text_layout' => 'diagonal'])->saveQuietly();

    $node = app(HomepageService::class)->banners()[0];

    expect($node['button_link'])->toBe('/products');
    expect($node['layout'])->toBe('single');
    expect($node['text_layout'])->toBe('split');
});

it('isolates banners per store', function () {
    $storeA = Store::factory()->create(['slug' => 'banner-a']);
    $storeB = Store::factory()->create(['slug' => 'banner-b']);

    app(CurrentStore::class)->set($storeA);
    Banner::factory()->create(['title' => 'Mine']);

    app(CurrentStore::class)->set($storeB);
    expect(app(HomepageService::class)->banners())->toBe([]);

    app(CurrentStore::class)->set($storeA);
    expect(array_column(app(HomepageService::class)->banners(), 'title'))->toBe(['Mine']);

    app(CurrentStore::class)->forget();
});

test('admin manages banners from the homepage page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.homepage.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/homepage/index')
            ->has('banners')
            ->has('bannerLayouts')
            ->has('bannerTextLayouts')
        );
});

test('admin can create a banner with uploaded images', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(route('admin.banners.store'), [
        'title' => 'Festival',
        'subtitle' => 'Big deals',
        'button_label' => 'Shop now',
        'button_link' => '/products',
        'layout' => 'double',
        'text_layout' => 'split',
        'image_files' => [
            UploadedFile::fake()->image('one.jpg', 800, 600)->size(200),
            UploadedFile::fake()->image('two.jpg', 800, 600)->size(200),
        ],
        'is_active' => true,
        'sort_order' => 0,
    ])->assertRedirect();

    $banner = Banner::where('title', 'Festival')->firstOrFail();

    expect($banner->imageList())->toHaveCount(2);
    foreach ($banner->imageList() as $path) {
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $path));
    }
});

test('admin cannot save unsafe banner links or layouts', function () {
    $this->actingAs($this->admin)->post(route('admin.banners.store'), [
        'title' => 'Evil',
        'button_link' => 'javascript:alert(1)',
        'layout' => 'masonry',
        'text_layout' => 'diagonal',
    ])->assertSessionHasErrors(['button_link', 'layout', 'text_layout']);

    expect(Banner::where('title', 'Evil')->exists())->toBeFalse();
});

test('admin can update banners, keeping and removing images', function () {
    Storage::fake('public');
    $banner = Banner::factory()->create(['title' => 'Old', 'images' => ['/storage/banners/keep.jpg', '/storage/banners/drop.jpg']]);
    Storage::disk('public')->put('banners/keep.jpg', 'x');
    Storage::disk('public')->put('banners/drop.jpg', 'x');

    $this->actingAs($this->admin)->post(route('admin.banners.update', $banner), [
        '_method' => 'PUT',
        'title' => 'New',
        'layout' => 'single',
        'text_layout' => 'center',
        'kept_images' => ['/storage/banners/keep.jpg'],
        'image_files' => [UploadedFile::fake()->image('new.jpg', 800, 600)->size(200)],
    ])->assertRedirect();

    $banner->refresh();

    expect($banner->title)->toBe('New');
    expect($banner->imageList())->toHaveCount(2);
    expect($banner->imageList()[0])->toBe('/storage/banners/keep.jpg');
    Storage::disk('public')->assertMissing('banners/drop.jpg');
});

test('admin cannot inject foreign image paths', function () {
    $banner = Banner::factory()->create(['images' => []]);

    $this->actingAs($this->admin)->post(route('admin.banners.update', $banner), [
        '_method' => 'PUT',
        'title' => $banner->title,
        'layout' => 'single',
        'text_layout' => 'split',
        'kept_images' => ['/storage/elsewhere/evil.jpg'],
    ])->assertRedirect();

    expect($banner->fresh()->imageList())->toBe([]);
});

test('admin can toggle and delete banners', function () {
    Storage::fake('public');
    $banner = Banner::factory()->create(['images' => ['/storage/banners/gone.jpg']]);
    Storage::disk('public')->put('banners/gone.jpg', 'x');

    $this->actingAs($this->admin)->post(route('admin.banners.toggle', $banner))->assertRedirect();
    expect($banner->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.banners.destroy', $banner))->assertRedirect();
    expect(Banner::whereKey($banner->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('banners/gone.jpg');
});

test('staff without the homepage permission cannot manage banners', function () {
    $user = User::factory()->create();
    $user->assignRole('support');

    $this->actingAs($user)->get(route('admin.homepage.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.banners.store'), ['title' => 'Nope'])->assertForbidden();
});

test('storefront banners endpoint serves active banners', function () {
    Banner::factory()->create(['title' => 'Live', 'layout' => 'double', 'text_layout' => 'center']);
    Banner::factory()->create(['title' => 'Draft', 'is_active' => false]);

    $response = $this->getJson('/api/banners')->assertOk();

    expect(array_column($response->json('data'), 'title'))->toBe(['Live']);
});

it('serves visibility flags and iframe embeds', function () {
    Banner::factory()->create([
        'title' => 'Video',
        'media_type' => 'iframe',
        'iframe_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'show_title' => false,
        'show_subtitle' => true,
        'show_button' => false,
    ]);

    $node = $this->getJson('/api/banners')->assertOk()->json('data.0');

    expect($node)->toMatchArray([
        'title' => 'Video',
        'media_type' => 'iframe',
        'iframe_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'show_title' => false,
        'show_subtitle' => true,
        'show_button' => false,
    ]);
});

it('rejects non-https embed URLs', function () {
    $this->actingAs($this->admin)->post(route('admin.banners.store'), [
        'title' => 'Bad Embed',
        'media_type' => 'iframe',
        'iframe_url' => 'javascript:alert(1)',
        'layout' => 'single',
        'text_layout' => 'split',
    ])->assertSessionHasErrors(['iframe_url']);

    expect(Banner::where('title', 'Bad Embed')->exists())->toBeFalse();
});

it('saves an iframe banner end to end', function () {
    $this->actingAs($this->admin)->post(route('admin.banners.store'), [
        'title' => 'Look',
        'media_type' => 'iframe',
        'iframe_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'layout' => 'single',
        'text_layout' => 'center',
        'show_title' => false,
        'show_subtitle' => true,
        'show_button' => false,
    ])->assertRedirect();

    $this->assertDatabaseHas('banners', [
        'title' => 'Look',
        'media_type' => 'iframe',
        'iframe_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'show_title' => false,
    ]);
});

it('resolves watch, shorts, and share links into embeds with titles', function () {
    Http::fake([
        'youtube.com/oembed*' => Http::response(['title' => 'Never Gonna Give You Up', 'author_name' => 'Rick Astley'], 200),
    ]);

    $cases = [
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'https://youtu.be/dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/shorts/dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/embed/dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
    ];

    foreach ($cases as $input => $embed) {
        $response = $this->actingAs($this->admin)->postJson(route('admin.banners.fetch-embed'), ['url' => $input])->assertOk();

        expect($response->json('embed_url'))->toBe($embed)
            ->and($response->json('provider'))->toBe('youtube')
            ->and($response->json('title'))->toBe('Never Gonna Give You Up');
    }
});

it('passes map embeds through without a title', function () {
    $url = 'https://www.google.com/maps/embed?pb=!1m18!2m3';

    $response = $this->actingAs($this->admin)->postJson(route('admin.banners.fetch-embed'), ['url' => $url])->assertOk();

    expect($response->json('embed_url'))->toBe($url)
        ->and($response->json('provider'))->toBe('embed')
        ->and($response->json('title'))->toBeNull();
});

it('rejects unusable links without leaving the dialog flow', function () {
    foreach (['javascript:alert(1)', 'not a url', 'https://www.youtube.com/watch', 'http://insecure.com/x'] as $bad) {
        $this->actingAs($this->admin)->postJson(route('admin.banners.fetch-embed'), ['url' => $bad])
            ->assertJsonValidationErrors(['url']);
    }
});

it('still resolves embeds when oEmbed lookup fails', function () {
    Http::fake(['youtube.com/oembed*' => Http::response([], 500)]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.banners.fetch-embed'), ['url' => 'https://youtu.be/dQw4w9WgXcQ'])
        ->assertOk();

    expect($response->json('embed_url'))->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ')
        ->and($response->json('title'))->toBeNull();
});

test('staff without the homepage permission cannot fetch embeds', function () {
    $user = User::factory()->create();
    $user->assignRole('support');

    $this->actingAs($user)->postJson(route('admin.banners.fetch-embed'), ['url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertForbidden();
});
