<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\User;

it('renders the account dashboard page', function () {
    $response = $this->get('/account');

    $response->assertOk()->assertInertia(fn ($p) => $p->component('account/index'));
});

it('renders the account orders page', function () {
    $response = $this->get('/account/orders');

    $response->assertOk()->assertInertia(fn ($p) => $p->component('account/orders/index'));
});

it('renders the account order detail page with the order id', function () {
    $response = $this->get('/account/orders/42');

    $response->assertOk()->assertInertia(fn ($p) => $p->component('account/orders/show')->where('orderId', 42));
});

it('renders the account addresses page', function () {
    $response = $this->get('/account/addresses');

    $response->assertOk()->assertInertia(fn ($p) => $p->component('account/addresses/index'));
});

it('returns 401 for guest order listing', function () {
    $this->getJson('/api/orders')->assertStatus(401);
});

it('returns 422 when cancelling a delivered order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->delivered()->for($user)->create();

    $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

    $response->assertStatus(422);
    expect($order->fresh()->status)->toBe('delivered');
});

it('resets the previous default when creating a new default address', function () {
    $user = User::factory()->create();
    $old = Address::factory()->default()->for($user)->create();

    $response = $this->actingAs($user)->postJson('/api/addresses', [
        'name' => 'Work',
        'phone' => '01700000000',
        'address' => '456 Office Road',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'is_default' => true,
    ]);

    $response->assertCreated();
    expect($old->fresh()->is_default)->toBeFalse();
    $this->assertDatabaseHas('addresses', [
        'user_id' => $user->id,
        'name' => 'Work',
        'is_default' => true,
    ]);
});

it('forbids updating another users address with 403', function () {
    $user = User::factory()->create();
    $address = Address::factory()->for(User::factory()->create())->create();

    $this->actingAs($user)->patchJson("/api/addresses/{$address->id}", [
        'city' => 'Chattogram',
    ])->assertStatus(403);
});

it('forbids deleting another users address with 403', function () {
    $user = User::factory()->create();
    $address = Address::factory()->for(User::factory()->create())->create();

    $this->actingAs($user)->deleteJson("/api/addresses/{$address->id}")->assertStatus(403);
});

it('returns 422 when creating an address with an empty payload', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/addresses', []);

    $response->assertStatus(422)->assertJsonValidationErrors(['name', 'phone', 'address', 'city', 'state']);
});

it('renders the wishlist page shell for guests', function () {
    $this->get('/wishlist')->assertOk()->assertInertia(
        fn ($p) => $p->component('wishlist/index')
    );
});

it('renders the wishlist page for storefront token-cookie users', function () {
    $user = createUser();
    $token = $user->createToken('storefront')->plainTextToken;

    $this->withCookie('store_token', $token)->get('/wishlist')->assertOk()->assertInertia(
        fn ($p) => $p->component('wishlist/index')
    );
});
