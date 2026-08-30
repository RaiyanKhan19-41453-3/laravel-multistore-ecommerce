<?php

use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super-admin');

    $this->product = Product::factory()->create(['type' => 'simple']);
    $this->inventory = Inventory::factory()->forProduct($this->product)->withQuantity(50)->create();
});

test('admin can view inventory page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('admin.inventory.index'));

    $response->assertOk();
});

test('admin can adjust stock positively', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.inventory.adjust', $this->inventory), [
            'type' => 'purchase',
            'quantity' => 20,
            'note' => 'Restocked',
        ]);

    $response->assertRedirect();

    $this->inventory->refresh();
    $this->assertEquals(70, $this->inventory->quantity);

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_id' => $this->inventory->id,
        'type' => 'purchase',
        'quantity' => 20,
        'note' => 'Restocked',
        'user_id' => $this->user->id,
    ]);
});

test('admin can adjust stock negatively', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.inventory.adjust', $this->inventory), [
            'type' => 'sale',
            'quantity' => -10,
            'note' => 'Sold 10 units',
        ]);

    $response->assertRedirect();

    $this->inventory->refresh();
    $this->assertEquals(40, $this->inventory->quantity);
});

test('cannot reduce stock below zero', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.inventory.adjust', $this->inventory), [
            'type' => 'sale',
            'quantity' => -100,
        ]);

    $response->assertInvalid(['quantity']);

    $this->inventory->refresh();
    $this->assertEquals(50, $this->inventory->quantity);
});

test('admin can view movement history', function () {
    $this->actingAs($this->user)
        ->post(route('admin.inventory.adjust', $this->inventory), [
            'type' => 'purchase',
            'quantity' => 10,
        ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.inventory.movements', $this->inventory));

    $response->assertOk();
    $response->assertJsonStructure([
        'movements' => [
            '*' => ['id', 'type', 'quantity', 'note', 'user_name', 'created_at'],
        ],
    ]);
});

test('movement records include user who performed the adjustment', function () {
    $this->actingAs($this->user)
        ->post(route('admin.inventory.adjust', $this->inventory), [
            'type' => 'purchase',
            'quantity' => 10,
        ]);

    $movement = $this->inventory->movements()->latest()->first();
    $this->assertEquals($this->user->id, $movement->user_id);
    $this->assertEquals($this->user->name, $movement->user->name);
});

test('cannot validate invalid adjustment type', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.inventory.adjust', $this->inventory), [
            'type' => 'invalid_type',
            'quantity' => 10,
        ]);

    $response->assertInvalid(['type']);
});

test('unauthenticated user cannot view inventory', function () {
    $response = $this->get(route('admin.inventory.index'));

    $response->assertRedirect('/admin/login');
});

test('non-admin user cannot view inventory', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('admin.inventory.index'));

    $response->assertForbidden();
});
