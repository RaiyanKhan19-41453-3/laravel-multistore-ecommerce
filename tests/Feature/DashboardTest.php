<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

test('guests are redirected to the login page', function () {
    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
});

test('non-admin users are forbidden from the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/dashboard')->assertForbidden();
});

test('super-admin users can visit the dashboard', function () {
    $this->seed(PermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user);

    $this->get('/admin/dashboard')->assertOk();
});
