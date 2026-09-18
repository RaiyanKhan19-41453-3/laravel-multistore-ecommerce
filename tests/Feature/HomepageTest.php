<?php

use App\Models\Banner;
use App\Models\HeroSlide;
use App\Models\HomepageSection;
use App\Models\Store;
use App\Models\User;
use App\Services\CatalogCache;
use App\Services\HomepageService;
use App\Services\SettingsService;
use App\Support\CurrentStore;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Cache::flush();
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
});

it('returns the canonical section order when nothing is saved', function () {
    expect(app(HomepageService::class)->sections())->toBe(HomepageSection::KEYS);
});

it('respects the saved order and hides inactive sections', function () {
    HomepageSection::factory()->create(['key' => 'perks', 'sort_order' => 0]);
    HomepageSection::factory()->create(['key' => 'hero', 'sort_order' => 1]);
    HomepageSection::factory()->create(['key' => 'featured', 'sort_order' => 2, 'is_active' => false]);

    $sections = app(HomepageService::class)->sections();

    expect($sections[0])->toBe('perks');
    expect($sections[1])->toBe('hero');
    expect($sections)->not->toContain('featured');
});

it('isolates homepage layouts per store', function () {
    $storeA = Store::factory()->create(['slug' => 'home-a']);
    $storeB = Store::factory()->create(['slug' => 'home-b']);

    app(CurrentStore::class)->set($storeA);
    HomepageSection::factory()->create(['key' => 'perks', 'sort_order' => 0, 'is_active' => false]);

    app(CurrentStore::class)->set($storeB);
    expect(app(HomepageService::class)->sections())->toBe(HomepageSection::KEYS);

    app(CurrentStore::class)->set($storeA);
    expect(app(HomepageService::class)->sections())->not->toContain('perks');

    app(CurrentStore::class)->forget();
});

test('admin can view the homepage manager', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.homepage.index'))
        ->assertOk();
});

test('admin can save the homepage layout', function () {
    $banner = Banner::factory()->create(['title' => 'Placed Banner']);
    $payload = [
        ['type' => 'section', 'key' => 'hero', 'banner_id' => null, 'is_active' => true, 'sort_order' => 0],
        ['type' => 'banner', 'key' => null, 'banner_id' => $banner->id, 'is_active' => true, 'sort_order' => 1],
        ['type' => 'section', 'key' => 'perks', 'banner_id' => null, 'is_active' => false, 'sort_order' => 2],
    ];

    $this->actingAs($this->admin)
        ->putJson(route('admin.homepage.update'), ['hero_display' => 'split', 'blocks' => $payload])
        ->assertRedirect();

    $blocks = app(HomepageService::class)->blocks();

    expect($blocks)->toHaveCount(2);
    expect($blocks[0])->toMatchArray(['type' => 'section', 'key' => 'hero']);
    expect($blocks[1]['type'])->toBe('banner');
    expect($blocks[1]['banner']['title'])->toBe('Placed Banner');
    $this->assertDatabaseHas('homepage_sections', ['key' => 'hero', 'is_active' => true]);
    $this->assertDatabaseHas('homepage_sections', ['banner_id' => $banner->id, 'is_active' => true]);
});

test('admin cannot save duplicate or unknown blocks', function () {
    $banner = Banner::factory()->create();

    $this->actingAs($this->admin)
        ->putJson(route('admin.homepage.update'), [
            'hero_display' => 'split',
            'blocks' => [
                ['type' => 'section', 'key' => 'hero', 'banner_id' => null, 'is_active' => true, 'sort_order' => 0],
                ['type' => 'section', 'key' => 'hero', 'banner_id' => null, 'is_active' => true, 'sort_order' => 1],
            ],
        ])
        ->assertJsonValidationErrors(['blocks']);

    $this->actingAs($this->admin)
        ->putJson(route('admin.homepage.update'), [
            'hero_display' => 'split',
            'blocks' => [
                ['type' => 'banner', 'key' => null, 'banner_id' => $banner->id, 'is_active' => true, 'sort_order' => 0],
                ['type' => 'banner', 'key' => null, 'banner_id' => $banner->id, 'is_active' => true, 'sort_order' => 1],
            ],
        ])
        ->assertJsonValidationErrors(['blocks']);

    $this->actingAs($this->admin)
        ->putJson(route('admin.homepage.update'), [
            'hero_display' => 'split',
            'blocks' => [
                ['type' => 'section', 'key' => 'nope', 'banner_id' => null, 'is_active' => true, 'sort_order' => 0],
            ],
        ])
        ->assertJsonValidationErrors(['blocks.0.key']);
});

test('storefront blocks endpoint interleaves banners at saved positions', function () {
    $first = Banner::factory()->create(['title' => 'After Hero', 'sort_order' => 0]);
    Banner::factory()->create(['title' => 'Elsewhere', 'sort_order' => 1]);

    $this->actingAs($this->admin)->putJson(route('admin.homepage.update'), [
        'hero_display' => 'split',
        'blocks' => [
            ['type' => 'section', 'key' => 'hero', 'banner_id' => null, 'is_active' => true, 'sort_order' => 0],
            ['type' => 'banner', 'key' => null, 'banner_id' => $first->id, 'is_active' => true, 'sort_order' => 1],
            ['type' => 'section', 'key' => 'perks', 'banner_id' => null, 'is_active' => true, 'sort_order' => 2],
        ],
    ])->assertRedirect();

    $response = $this->getJson('/api/homepage/blocks')->assertOk();
    $blocks = $response->json('data');

    expect(array_map(fn ($b) => $b['type'] === 'banner' ? $b['banner']['title'] : $b['key'], $blocks))
        ->toBe(['hero', 'After Hero', 'perks']);
});

test('staff without the homepage permission cannot manage the layout', function () {
    $user = User::factory()->create();
    $user->assignRole('support');

    $this->actingAs($user)
        ->get(route('admin.homepage.index'))
        ->assertForbidden();
});

test('storefront homepage endpoint serves active sections in order', function () {
    HomepageSection::factory()->create(['key' => 'featured', 'sort_order' => 0]);
    HomepageSection::factory()->create(['key' => 'hero', 'sort_order' => 1]);

    $this->getJson('/api/homepage')
        ->assertOk()
        ->assertJsonPath('data.0', 'featured')
        ->assertJsonPath('data.1', 'hero');
});

test('storefront homepage endpoint falls back to defaults', function () {
    $this->getJson('/api/homepage')
        ->assertOk()
        ->assertJsonPath('data', HomepageSection::KEYS);
});

it('returns split display with no slides by default', function () {
    $this->getJson('/api/hero')
        ->assertOk()
        ->assertJsonPath('data.display', 'split')
        ->assertJsonPath('data.slides', []);
});

it('serves the saved slider with localized slides', function () {
    HeroSlide::factory()->create([
        'title' => 'Big Sale',
        'title_ar' => 'تخفيضات كبيرة',
        'subtitle' => 'Up to half price',
        'cta_label' => 'Shop now',
        'cta_link' => '/products',
        'image' => '/storage/hero/sale.jpg',
        'sort_order' => 0,
    ]);
    HeroSlide::factory()->create(['title' => 'Hidden', 'is_active' => false]);

    app(HomepageService::class)->saveHeroDisplay('slider');

    $response = $this->getJson('/api/hero')->assertOk();

    expect($response->json('data.display'))->toBe('slider');
    expect($response->json('data.slides'))->toHaveCount(1);
    expect($response->json('data.slides.0'))->toMatchArray([
        'title' => 'Big Sale',
        'subtitle' => 'Up to half price',
        'cta_label' => 'Shop now',
        'cta_link' => '/products',
        'image' => '/storage/hero/sale.jpg',
    ]);

    app()->setLocale('ar');
    expect($this->getJson('/api/hero')->json('data.slides.0.title'))->toBe('تخفيضات كبيرة');
    app()->setLocale('en');
});

it('falls back to split for an unknown saved display', function () {
    app(SettingsService::class)->set('homepage.hero_display', 'hologram', 'homepage');

    expect(app(HomepageService::class)->heroDisplay())->toBe('split');
});

it('serves per-slide visibility flags', function () {
    HeroSlide::factory()->create([
        'title' => 'Quiet Slide',
        'show_eyebrow' => false,
        'show_title' => true,
        'show_subtitle' => false,
        'show_button' => false,
    ]);

    $node = $this->getJson('/api/hero')->assertOk()->json('data.slides.0');

    expect($node)->toMatchArray([
        'title' => 'Quiet Slide',
        'show_eyebrow' => false,
        'show_title' => true,
        'show_subtitle' => false,
        'show_button' => false,
    ]);
});

test('admin saves slide visibility flags', function () {
    $admin = createAdmin();
    $slide = HeroSlide::factory()->create(['title' => 'Toggle Me']);

    $this->actingAs($admin)->post(route('admin.homepage.slides.update', $slide), [
        '_method' => 'PUT',
        'title' => 'Toggle Me',
        'show_eyebrow' => false,
        'show_title' => true,
        'show_subtitle' => false,
        'show_button' => false,
    ])->assertRedirect();

    expect($slide->fresh()->toArray())->toMatchArray([
        'show_eyebrow' => false,
        'show_title' => true,
        'show_subtitle' => false,
        'show_button' => false,
    ]);
});

test('admin can create a slide with an image', function () {
    Storage::fake('public');
    $admin = createAdmin();

    $this->actingAs($admin)->post('/admin/homepage/slides', [
        'title' => 'Festival Sale',
        'subtitle' => 'Best prices of the year',
        'cta_label' => 'Shop now',
        'cta_link' => '/products',
        'image_file' => UploadedFile::fake()->image('hero.jpg', 1600, 900)->size(300),
        'is_active' => true,
        'sort_order' => 0,
    ])->assertRedirect();

    $slide = HeroSlide::where('title', 'Festival Sale')->firstOrFail();

    expect($slide->image)->toStartWith('/storage/hero/');
    expect($slide->layout)->toBe('split');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $slide->image));
});

it('saves and serves the full-bleed slide layout', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->post('/admin/homepage/slides', [
        'title' => 'Full Bleed',
        'layout' => 'full',
        'image_file' => UploadedFile::fake()->image('wide.jpg', 1600, 900)->size(300),
    ])->assertRedirect();

    $node = $this->getJson('/api/hero')->assertOk()->json('data.slides.0');

    expect($node)->toMatchArray(['title' => 'Full Bleed', 'layout' => 'full']);
});

it('rejects unknown slide layouts and falls back safely', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->post('/admin/homepage/slides', [
        'title' => 'Bad Layout',
        'layout' => 'hologram',
    ])->assertSessionHasErrors(['layout']);

    $slide = HeroSlide::factory()->create(['title' => 'Weird']);
    $slide->fill(['layout' => 'hologram'])->saveQuietly();

    expect($this->getJson('/api/hero')->json('data.slides.0.layout'))->toBe('split');
});

test('admin slide links must be safe', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->post('/admin/homepage/slides', [
        'title' => 'Evil',
        'cta_link' => 'javascript:alert(1)',
    ])->assertSessionHasErrors(['cta_link']);

    expect(HeroSlide::where('title', 'Evil')->exists())->toBeFalse();
});

test('admin can toggle and delete slides', function () {
    $admin = createAdmin();
    $slide = HeroSlide::factory()->create(['title' => 'Temp']);

    $this->actingAs($admin)->post(route('admin.homepage.slides.toggle', $slide))->assertRedirect();
    expect($slide->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->delete(route('admin.homepage.slides.destroy', $slide))->assertRedirect();
    expect(HeroSlide::whereKey($slide->id)->exists())->toBeFalse();
});

test('admin saves hero display with the layout', function () {
    $admin = createAdmin();
    $payload = collect(HomepageSection::KEYS)
        ->values()
        ->map(fn ($key, $i) => ['type' => 'section', 'key' => $key, 'banner_id' => null, 'is_active' => true, 'sort_order' => $i])
        ->all();

    $this->actingAs($admin)->putJson(route('admin.homepage.update'), [
        'hero_display' => 'centered',
        'blocks' => $payload,
    ])->assertRedirect();

    expect(app(HomepageService::class)->heroDisplay())->toBe('centered');
});

test('staff without the homepage permission cannot manage slides', function () {
    $user = createStaffUser('support');

    $this->actingAs($user)->post(route('admin.homepage.slides.store'), ['title' => 'Nope'])->assertForbidden();
});

it('ignores homepage orders cached before new section types existed', function () {
    $service = app(HomepageService::class);

    // Prime the versioned cache, then plant a legacy unversioned entry.
    expect($service->sections())->toBe(HomepageSection::KEYS);

    $version = substr(md5(implode(',', HomepageSection::KEYS)), 0, 8);
    $legacyKey = str_replace("{$version}.", '', CatalogCache::homepageKey());
    Cache::put($legacyKey, ['hero'], 3600);
    Cache::forget(CatalogCache::homepageKey());

    expect($service->sections())->toBe(HomepageSection::KEYS);
});
