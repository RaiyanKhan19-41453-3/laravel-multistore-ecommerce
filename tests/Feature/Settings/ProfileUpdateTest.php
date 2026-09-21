<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/settings/profile');

    $response->assertOk();
});

test('settings pages share the user permissions for the sidebar', function () {
    $staff = createStaffUser('support');

    $this->actingAs($staff)
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('orders.view')));
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/profile')
        ->delete('/settings/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($user->fresh())->not->toBeNull();
});

test('user can upload and remove a profile photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/profile', [
        '_method' => 'PATCH',
        'name' => $user->name,
        'email' => $user->email,
        'avatar_file' => UploadedFile::fake()->image('avatar.jpg', 200, 200)->size(100),
    ])->assertRedirect('/settings/profile');

    $avatar = $user->fresh()->avatar;
    expect($avatar)->toStartWith('/storage/avatars/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $avatar));

    // Shared props (sidebar avatar) expose it.
    $this->actingAs($user)->get('/settings/profile')->assertOk()
        ->assertInertia(fn ($p) => $p->where('auth.user.avatar', $avatar));

    // Replacing deletes the old file.
    $this->actingAs($user)->post('/settings/profile', [
        '_method' => 'PATCH',
        'name' => $user->name,
        'email' => $user->email,
        'avatar_file' => UploadedFile::fake()->image('avatar2.jpg', 200, 200)->size(100),
    ])->assertRedirect('/settings/profile');

    Storage::disk('public')->assertMissing(str_replace('/storage/', '', $avatar));

    // Removing clears it.
    $this->actingAs($user)->post('/settings/profile', [
        '_method' => 'PATCH',
        'name' => $user->name,
        'email' => $user->email,
        'remove_avatar' => true,
    ])->assertRedirect('/settings/profile');

    expect($user->fresh()->avatar)->toBeNull();
});

test('profile photo rejects non-images', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/profile', [
        '_method' => 'PATCH',
        'name' => $user->name,
        'email' => $user->email,
        'avatar_file' => UploadedFile::fake()->create('avatar.txt', 10),
    ])->assertSessionHasErrors(['avatar_file']);
});
