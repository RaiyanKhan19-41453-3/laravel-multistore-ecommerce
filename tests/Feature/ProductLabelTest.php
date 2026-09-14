<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BarcodeService;
use Spatie\Permission\Models\Role;

function createLabelAdmin(): User
{
    $user = User::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user->assignRole('super-admin');

    return $user;
}

it('resolves manual barcode over sku', function () {
    $service = new BarcodeService;
    $product = Product::factory()->create(['sku' => 'SKU-1', 'barcode' => 'MANUAL-9']);

    expect($service->resolveValue($product))->toBe('MANUAL-9');
});

it('falls back to sku when barcode is empty', function () {
    $service = new BarcodeService;
    $product = Product::factory()->create(['sku' => 'SKU-2', 'barcode' => null]);

    expect($service->resolveValue($product))->toBe('SKU-2');
});

it('returns null when neither barcode nor sku exists', function () {
    $service = new BarcodeService;
    $product = Product::factory()->make(['sku' => '', 'barcode' => '']);

    expect($service->resolveValue($product))->toBeNull();
});

it('renders scannable svg output', function () {
    $svg = (new BarcodeService)->svg('SKU-123');

    expect($svg)->toContain('<svg');
    expect($svg)->toContain('</svg>');
});

it('skips variable products and unprintable items', function () {
    $simple = Product::factory()->create(['type' => 'simple', 'sku' => 'SIMPLE-1', 'price' => 100]);
    $variable = Product::factory()->create(['type' => 'variable', 'sku' => 'VAR-1']);
    $blank = Product::factory()->create(['type' => 'simple', 'sku' => '', 'barcode' => '']);

    $labels = (new BarcodeService)->labelsForProducts(
        Product::whereIn('id', [$simple->id, $variable->id, $blank->id])->get(),
        2
    );

    expect($labels)->toHaveCount(2);
    expect($labels[0]['code'])->toBe('SIMPLE-1');
    expect($labels[0]['svg'])->toContain('<svg');
});

it('builds variant labels with parent product name', function () {
    $product = Product::factory()->create(['name' => 'Parent Tee', 'price' => 500]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Red / L',
        'sku' => 'TEE-RED-L',
        'price' => 550,
    ]);

    $labels = (new BarcodeService)->labelsForVariants(
        ProductVariant::where('id', $variant->id)->get()
    );

    expect($labels)->toHaveCount(1);
    expect($labels[0]['name'])->toBe('Parent Tee');
    expect($labels[0]['variant'])->toBe('Red / L');
    expect($labels[0]['price'])->toBe(550.0);
});

it('builds variant labels from plain collections', function () {
    $product = Product::factory()->create(['name' => 'Parent Tee', 'price' => 500]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Blue / M',
        'sku' => 'TEE-BLUE-M',
    ]);

    $labels = (new BarcodeService)->labelsForVariants(collect([$variant->fresh()]));

    expect($labels)->toHaveCount(1);
    expect($labels[0]['variant'])->toBe('Blue / M');
    expect($labels[0]['code'])->toBe('TEE-BLUE-M');
});

it('admin can open the label picker', function () {
    $admin = createLabelAdmin();
    $product = Product::factory()->create(['type' => 'variable', 'name' => 'Picker Tee']);
    ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Red / L', 'sku' => 'PICK-RED-L']);

    $response = $this->actingAs($admin)->get('/admin/products/labels');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('products')
        ->has('layouts')
        ->where('defaultLayout', 'a4-40')
    );

    $props = $response->viewData('page')['props'];
    $found = collect($props['products'])->firstWhere('id', $product->id);
    expect($found['variants'])->toHaveCount(1);
    expect($found['variants'][0]['printable'])->toBeTrue();
});

it('guests cannot open the label picker', function () {
    $this->get('/admin/products/labels')->assertRedirect('/admin/login');
});

it('print endpoint renders requested labels', function () {
    $admin = createLabelAdmin();
    $product = Product::factory()->create(['type' => 'simple', 'sku' => 'PRINT-1', 'price' => 250]);

    $response = $this->actingAs($admin)->getJson('/admin/products/labels/print?items=p:'.$product->id.':3&layout=a4-40');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('labels', 3)
        ->where('layout', 'a4-40')
        ->where('labels.0.code', 'PRINT-1')
    );
});

it('print endpoint renders variant labels with quantities', function () {
    $admin = createLabelAdmin();
    $product = Product::factory()->create(['type' => 'variable', 'name' => 'Var Tee']);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Green / S',
        'sku' => 'VAR-GREEN-S',
        'price' => 300,
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/products/labels/print?items=v:'.$variant->id.':2&layout=thermal-50x30');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('labels', 2)
        ->where('layout', 'thermal-50x30')
        ->where('labels.0.code', 'VAR-GREEN-S')
        ->where('labels.0.variant', 'Green / S')
        ->where('labels.0.price', 300)
    );
});

it('print endpoint reports skipped items instead of failing', function () {
    $admin = createLabelAdmin();
    $variable = Product::factory()->create(['type' => 'variable', 'name' => 'Var Product', 'sku' => 'VAR-9']);

    $response = $this->actingAs($admin)->getJson('/admin/products/labels/print?items=p:'.$variable->id.':1,p:99999:1&layout=a4-24');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('labels', 0)
        ->has('skipped', 2)
    );
});

it('print endpoint rejects bad layout and malformed items', function () {
    $admin = createLabelAdmin();

    $this->actingAs($admin)
        ->getJson('/admin/products/labels/print?items=p:1:1&layout=nope')
        ->assertStatus(422);

    $this->actingAs($admin)
        ->getJson('/admin/products/labels/print?layout=a4-40')
        ->assertStatus(422);
});

it('print endpoint ignores malformed item chunks gracefully', function () {
    $admin = createLabelAdmin();
    $product = Product::factory()->create(['type' => 'simple', 'sku' => 'FUZZ-1', 'price' => 100]);

    $response = $this->actingAs($admin)->getJson(
        '/admin/products/labels/print?items=p:abc:1,x:1:1,p:1,p:'.$product->id.':99999,,&layout=a4-40'
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('labels', 100)
        ->where('labels.0.code', 'FUZZ-1')
    );
});
