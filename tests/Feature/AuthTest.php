<?php

use App\Models\User;

it('check-email returns true for registered email', function () {
    User::factory()->create(['email' => 'registered@example.com']);

    $response = $this->postJson('/api/auth/check-email', [
        'email' => 'registered@example.com',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'exists' => true,
        ],
    ]);
});

it('check-email returns false for unregistered email', function () {
    $response = $this->postJson('/api/auth/check-email', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'exists' => false,
        ],
    ]);
});

it('check-email normalizes email', function () {
    User::factory()->create(['email' => 'lower@example.com']);

    $response = $this->postJson('/api/auth/check-email', [
        'email' => 'LOWER@EXAMPLE.COM',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'exists' => true,
        ],
    ]);
});

it('check-email requires email field', function () {
    $response = $this->postJson('/api/auth/check-email', []);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('check-email validates email format', function () {
    $response = $this->postJson('/api/auth/check-email', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});
