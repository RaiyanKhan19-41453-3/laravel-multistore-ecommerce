<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\MenuService;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    Cache::flush();

    $this->admin = createAdmin();
});

function menuCategory(string $name = 'Shoes'): Category
{
    return Category::create(['name' => $name, 'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5), 'is_active' => true]);
}

function menuBrand(string $name = 'Acme'): Brand
{
    return Brand::create(['name' => $name, 'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5), 'is_active' => true]);
}

it('resolves every link type into an ordered tree', function () {
    $category = menuCategory();
    $brand = menuBrand();
    $product = Product::factory()->create(['is_active' => true]);
    $page = CmsPage::factory()->create(['is_published' => true]);

    $shop = MenuItem::factory()->create(['title' => 'Shop', 'type' => 'url', 'url' => '/products', 'sort_order' => 1]);
    MenuItem::factory()->create([
        'title' => 'Shoes',
        'type' => 'category',
        'reference_id' => $category->id,
        'parent_id' => $shop->id,
        'sort_order' => 2,
    ]);
    MenuItem::factory()->create([
        'title' => 'Brand',
        'type' => 'brand',
        'reference_id' => $brand->id,
        'parent_id' => $shop->id,
        'sort_order' => 1,
    ]);
    MenuItem::factory()->create(['title' => 'Tee', 'type' => 'product', 'reference_id' => $product->id, 'sort_order' => 2]);
    MenuItem::factory()->create(['title' => 'About', 'type' => 'page', 'reference_id' => $page->id, 'sort_order' => 3]);

    $tree = app(MenuService::class)->tree();
    $byTitle = collect($tree)->keyBy('title');

    expect($tree)->toHaveCount(3);
    expect($byTitle['Shop']['url'])->toBe('/products');
    expect($byTitle['Shop']['has_children'])->toBeTrue();
    expect(array_column($byTitle['Shop']['children'], 'title'))->toBe(['Brand', 'Shoes']);
    expect($byTitle['Shop']['children'][0]['url'])->toBe("/brands/{$brand->slug}");
    expect($byTitle['Tee']['url'])->toBe("/products/{$product->slug}");
    expect($byTitle['About']['url'])->toBe("/pages/{$page->slug}");
});

it('skips inactive items and dead targets', function () {
    $live = menuCategory('Live');
    $dead = menuCategory('Dead');
    $dead->update(['is_active' => false]);

    MenuItem::factory()->create(['title' => 'Live', 'type' => 'category', 'reference_id' => $live->id]);
    MenuItem::factory()->create(['title' => 'Dead', 'type' => 'category', 'reference_id' => $dead->id]);
    MenuItem::factory()->create(['title' => 'Gone', 'type' => 'product', 'reference_id' => 999999]);
    MenuItem::factory()->create(['title' => 'Hidden', 'type' => 'url', 'url' => '/x', 'is_active' => false]);
    MenuItem::factory()->create(['title' => 'Draft', 'type' => 'page', 'reference_id' => CmsPage::factory()->create(['is_published' => false])->id]);

    $titles = array_column(app(MenuService::class)->tree(), 'title');

    expect($titles)->toBe(['Live']);
});

it('passes click behavior, display mode, and promo through', function () {
    $parent = MenuItem::factory()->create([
        'title' => 'Shop',
        'type' => 'url',
        'url' => '/products',
        'click_behavior' => 'expand',
        'display' => 'mega',
        'promo_title' => 'Sale',
        'promo_image' => '/storage/promo.jpg',
        'promo_link' => '/sale',
    ]);
    MenuItem::factory()->create(['title' => 'Kid', 'type' => 'url', 'url' => '/k', 'parent_id' => $parent->id]);

    $node = app(MenuService::class)->tree()[0];

    expect($node['click_behavior'])->toBe('expand');
    expect($node['display'])->toBe('mega');
    expect($node['promo'])->toBe(['image' => '/storage/promo.jpg', 'title' => 'Sale', 'link' => '/sale']);
});

it('isolates menus per store and skips cross-store targets', function () {
    $storeA = Store::factory()->create(['slug' => 'menu-a']);
    $storeB = Store::factory()->create(['slug' => 'menu-b']);

    app(CurrentStore::class)->set($storeA);
    $categoryA = Category::create(['name' => 'Shoes', 'slug' => 'shoes', 'is_active' => true]);
    MenuItem::factory()->create(['title' => 'Mine', 'type' => 'category', 'reference_id' => $categoryA->id]);
    MenuItem::factory()->create(['title' => 'Theirs', 'type' => 'category', 'reference_id' => 999001, 'store_id' => $storeA->id]);

    app(CurrentStore::class)->set($storeB);
    $categoryB = Category::create(['name' => 'Hats', 'slug' => 'hats', 'is_active' => true]);
    MenuItem::factory()->create(['title' => 'Other', 'type' => 'category', 'reference_id' => $categoryB->id]);
    // Points at store A's category while living in store B: skipped.
    MenuItem::factory()->create(['title' => 'Leak', 'type' => 'category', 'reference_id' => $categoryA->id]);

    app(CurrentStore::class)->set($storeA);
    expect(array_column(app(MenuService::class)->tree(), 'title'))->toBe(['Mine']);

    app(CurrentStore::class)->set($storeB);
    expect(array_column(app(MenuService::class)->tree(), 'title'))->toBe(['Other']);

    app(CurrentStore::class)->forget();
});

it('localizes titles when the locale is Arabic', function () {
    MenuItem::factory()->create(['title' => 'Shop', 'title_ar' => 'تسوّق', 'type' => 'url', 'url' => '/products']);

    app()->setLocale('ar');
    expect(app(MenuService::class)->tree()[0]['title'])->toBe('تسوّق');
    app()->setLocale('en');
});

it('rejects dangerous custom URLs', function () {
    expect(MenuService::safeUrl('javascript:alert(1)'))->toBeNull();
    expect(MenuService::safeUrl('data:text/html,hi'))->toBeNull();
    expect(MenuService::safeUrl('//evil.com/x'))->toBeNull();
    expect(MenuService::safeUrl('/products'))->toBe('/products');
    expect(MenuService::safeUrl('https://example.com/x'))->toBe('https://example.com/x');
});

test('admin can view menus page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.menus.index'))
        ->assertOk();
});

test('menus page props always carry a children array (no black screen)', function () {
    $parent = MenuItem::factory()->create(['title' => 'Shop', 'type' => 'url', 'url' => '/products']);
    MenuItem::factory()->create(['title' => 'Kid', 'type' => 'url', 'url' => '/k', 'parent_id' => $parent->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.menus.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/menus/index')
            ->has('items', 1)
            ->has('items.0.children', 1)
            ->has('items.0.children.0.children', 0)
        );
});

test('admin can create a parent with children', function () {
    $category = menuCategory();

    $this->actingAs($this->admin)
        ->post(route('admin.menus.store'), [
            'title' => 'Shop',
            'type' => 'url',
            'url' => '/products',
            'click_behavior' => 'navigate',
            'display' => 'mega',
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect();

    $parent = MenuItem::where('title', 'Shop')->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('admin.menus.store'), [
            'title' => 'Shoes',
            'type' => 'category',
            'reference_id' => $category->id,
            'parent_id' => $parent->id,
            'click_behavior' => 'navigate',
            'display' => 'auto',
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('menu_items', ['title' => 'Shoes', 'parent_id' => $parent->id, 'reference_id' => $category->id]);
    expect($parent->fresh()->children)->toHaveCount(1);
});

test('admin cannot save a script URL', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.menus.store'), [
            'title' => 'Evil',
            'type' => 'url',
            'url' => 'javascript:alert(1)',
            'click_behavior' => 'navigate',
            'display' => 'auto',
        ])
        ->assertSessionHasErrors('url');

    expect(MenuItem::where('title', 'Evil')->exists())->toBeFalse();
});

test('admin cannot nest three levels deep', function () {
    $root = MenuItem::factory()->create(['title' => 'Root', 'type' => 'url', 'url' => '/']);
    $child = MenuItem::factory()->create(['title' => 'Child', 'type' => 'url', 'url' => '/c', 'parent_id' => $root->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.menus.store'), [
            'title' => 'Grandchild',
            'type' => 'url',
            'url' => '/g',
            'parent_id' => $child->id,
            'click_behavior' => 'navigate',
            'display' => 'auto',
        ])
        ->assertSessionHasErrors('parent_id');
});

test('admin cannot move an item with children under another item', function () {
    $root = MenuItem::factory()->create(['title' => 'Root', 'type' => 'url', 'url' => '/']);
    $parent = MenuItem::factory()->create(['title' => 'Parent', 'type' => 'url', 'url' => '/p']);
    MenuItem::factory()->create(['title' => 'Kid', 'type' => 'url', 'url' => '/k', 'parent_id' => $parent->id]);

    $this->actingAs($this->admin)
        ->put(route('admin.menus.update', $parent), [
            'title' => 'Parent',
            'type' => 'url',
            'url' => '/p',
            'parent_id' => $root->id,
            'click_behavior' => 'navigate',
            'display' => 'auto',
        ])
        ->assertSessionHasErrors('parent_id');
});

test('admin cannot link a record from another store', function () {
    $storeA = Store::factory()->create(['slug' => 'menu-admin-a']);
    $storeB = Store::factory()->create(['slug' => 'menu-admin-b']);

    app(CurrentStore::class)->set($storeB);
    $foreign = Category::create(['name' => 'Foreign', 'slug' => 'foreign', 'is_active' => true]);

    // The admin store selection points at store A.
    app(CurrentStore::class)->set($storeA);

    $this->actingAs($this->admin)
        ->post(route('admin.menus.store'), [
            'title' => 'Sneaky',
            'type' => 'category',
            'reference_id' => $foreign->id,
            'click_behavior' => 'navigate',
            'display' => 'auto',
        ])
        ->assertSessionHasErrors('reference_id');

    app(CurrentStore::class)->forget();
});

test('admin can toggle and delete menu items', function () {
    $parent = MenuItem::factory()->create(['title' => 'Shop', 'type' => 'url', 'url' => '/products']);
    $child = MenuItem::factory()->create(['title' => 'Kid', 'type' => 'url', 'url' => '/k', 'parent_id' => $parent->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.menus.toggle', $parent))
        ->assertRedirect();

    expect($parent->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)
        ->delete(route('admin.menus.destroy', $parent))
        ->assertRedirect();

    // Children cascade with the parent.
    expect(MenuItem::whereKey($child->id)->exists())->toBeFalse();
    expect(MenuItem::whereKey($parent->id)->exists())->toBeFalse();
});

test('staff without the menus permission cannot manage menus', function () {
    $user = createStaffUser('support');

    $this->actingAs($user)
        ->get(route('admin.menus.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.menus.store'), ['title' => 'Nope', 'type' => 'url', 'url' => '/x'])
        ->assertForbidden();
});

test('catalog managers can manage menus', function () {
    $user = createStaffUser('catalog-manager');

    $this->actingAs($user)
        ->get(route('admin.menus.index'))
        ->assertOk();
});

test('guests cannot open the menus page', function () {
    $this->get(route('admin.menus.index'))->assertRedirect('/admin/login');
});

test('storefront menus endpoint serves the tree', function () {
    $category = menuCategory();
    MenuItem::factory()->create(['title' => 'Shop', 'type' => 'url', 'url' => '/products', 'sort_order' => 0]);
    MenuItem::factory()->create(['title' => 'Shoes', 'type' => 'category', 'reference_id' => $category->id, 'sort_order' => 0]);

    $response = $this->getJson('/api/menus')->assertOk();

    expect($response->json('success'))->toBeTrue();
    expect(array_column($response->json('data'), 'title'))->toContain('Shop', 'Shoes');
});

test('storefront menus endpoint is empty when no items exist', function () {
    $this->getJson('/api/menus')
        ->assertOk()
        ->assertJsonPath('data', []);
});
