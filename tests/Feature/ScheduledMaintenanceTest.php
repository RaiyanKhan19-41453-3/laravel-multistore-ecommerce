<?php

use App\Models\Cart;
use App\Models\Order;

it('expires overdue pending orders via the schedule command', function () {
    $overdue = Order::factory()->create([
        'status' => 'pending',
        'expires_at' => now()->subMinute(),
    ]);
    $fresh = Order::factory()->create([
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->artisan('orders:expire')->assertSuccessful();

    expect($overdue->fresh()->status)->toBe('expired');
    expect($fresh->fresh()->status)->toBe('pending');
});

it('cleans up stale carts via the schedule command', function () {
    $stale = Cart::create([
        'status' => 'active',
        'guest_token' => 'stale-token-123',
        'expires_at' => now()->subHour(),
    ]);
    $active = Cart::create([
        'status' => 'active',
        'guest_token' => 'fresh-token-123',
        'expires_at' => now()->addHour(),
    ]);

    $this->artisan('carts:cleanup')->assertSuccessful();

    expect($stale->fresh()->status)->toBe('expired');
    expect($active->fresh()->status)->toBe('active');
});
