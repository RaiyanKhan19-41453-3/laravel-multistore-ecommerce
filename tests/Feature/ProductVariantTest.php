<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super-admin');

    $this->product = Product::factory()->create([
        'type' => 'variable',
    ]);
});

test('admin can view variants page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('admin.products.variants.index', $this->product));

    $response->assertOk();
});

test('admin can create a variant', function () {
    $attribute = Attribute::factory()->create();
    $value = AttributeValue::factory()->create(['attribute_id' => $attribute->id]);

    $response = $this->actingAs($this->user)
        ->postJson(route('admin.products.variants.store', $this->product), [
            'name' => 'Small / Red',
            'sku' => 'PROD-SM-RED',
            'price' => '29.99',
            'quantity' => 10,
            'is_active' => true,
            'attribute_value_ids' => [$value->id],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseHas('product_variants', [
        'product_id' => $this->product->id,
        'name' => 'Small / Red',
        'sku' => 'PROD-SM-RED',
    ]);
});

test('admin can update a variant', function () {
    $variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
    $attribute = Attribute::factory()->create();
    $value = AttributeValue::factory()->create(['attribute_id' => $attribute->id]);

    $response = $this->actingAs($this->user)
        ->putJson(route('admin.products.variants.update', [$this->product, $variant]), [
            'name' => 'Updated Name',
            'sku' => $variant->sku,
            'price' => '39.99',
            'quantity' => 20,
            'is_active' => true,
            'attribute_value_ids' => [$value->id],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseHas('product_variants', [
        'id' => $variant->id,
        'name' => 'Updated Name',
    ]);
});

test('admin can delete a variant', function () {
    $variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('admin.products.variants.destroy', [$this->product, $variant]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
});

test('variant must belong to product', function () {
    $otherProduct = Product::factory()->create(['type' => 'variable']);
    $variant = ProductVariant::factory()->create(['product_id' => $otherProduct->id]);
    $attribute = Attribute::factory()->create();
    $value = AttributeValue::factory()->create(['attribute_id' => $attribute->id]);

    $response = $this->actingAs($this->user)
        ->putJson(route('admin.products.variants.update', [$this->product, $variant]), [
            'name' => 'Updated',
            'sku' => $variant->sku,
            'price' => '10',
            'quantity' => 1,
            'is_active' => true,
            'attribute_value_ids' => [$value->id],
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Variant does not belong to this product.',
        ]);
});

test('admin can generate variants from attributes', function () {
    $attribute = Attribute::factory()->create();
    $value1 = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'slug' => 'red']);
    $value2 = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'slug' => 'blue']);

    $response = $this->actingAs($this->user)
        ->postJson(route('admin.products.variants.generate', $this->product), [
            'attribute_ids' => [$attribute->id],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseCount('product_variants', 2);
});

test('unauthenticated user cannot access variants', function () {
    $response = $this->get(route('admin.products.variants.index', $this->product));

    $response->assertRedirect('/admin/login');
});

test('non-admin user cannot access variants', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('admin.products.variants.index', $this->product));

    $response->assertForbidden();
});
