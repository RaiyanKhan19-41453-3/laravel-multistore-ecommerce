<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

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

it('forgot-password requires identifier field', function () {
    $response = $this->postJson('/api/auth/forgot-password', []);

    $response->assertStatus(422)->assertJsonValidationErrors(['identifier']);
});

it('forgot-password returns success for non-existent email', function () {
    $response = $this->postJson('/api/auth/forgot-password', [
        'identifier' => 'nonexistent@example.com',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'If an account exists with that identifier, a reset link has been sent.',
    ]);
});

it('forgot-password sends reset link for existing email user', function () {
    User::factory()->create(['email' => 'user@example.com']);

    $response = $this->postJson('/api/auth/forgot-password', [
        'identifier' => 'user@example.com',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
    ]);

    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => 'user@example.com',
    ]);
});

it('forgot-password normalizes email identifier', function () {
    User::factory()->create(['email' => 'user@example.com']);

    $this->postJson('/api/auth/forgot-password', [
        'identifier' => 'USER@EXAMPLE.COM',
    ])->assertOk();

    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => 'user@example.com',
    ]);
});

it('forgot-password returns success for non-existent phone', function () {
    $response = $this->postJson('/api/auth/forgot-password', [
        'identifier' => '01999999999',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'If an account exists with that identifier, a reset link has been sent.',
    ]);
});

it('forgot-password returns error when sms gateway not configured', function () {
    User::factory()->create(['phone' => '01712345678']);

    config(['sms.enabled' => ['twilio' => false, 'ssl_wireless' => false]]);

    $response = $this->postJson('/api/auth/forgot-password', [
        'identifier' => '01712345678',
    ]);

    $response->assertStatus(500)->assertJson([
        'success' => false,
        'message' => 'SMS service is not configured.',
    ]);
});

it('forgot-password sends otp for existing phone user', function () {
    User::factory()->create(['phone' => '01712345678']);

    config(['sms.enabled' => ['twilio' => true]]);
    config(['sms.gateways.twilio' => [
        'account_sid' => 'AC123',
        'auth_token' => 'token',
        'from_number' => '+1234567890',
    ]]);

    $response = $this->postJson('/api/auth/forgot-password', [
        'identifier' => '01712345678',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
    ]);

    $this->assertDatabaseHas('password_reset_otps', [
        'phone' => '01712345678',
    ]);
});

it('reset-password requires token and password', function () {
    $response = $this->postJson('/api/auth/reset-password', []);

    $response->assertStatus(422)->assertJsonValidationErrors(['token', 'password']);
});

it('reset-password validates password minimum length', function () {
    $response = $this->postJson('/api/auth/reset-password', [
        'token' => 'some-token',
        'email' => 'user@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['password']);
});

it('reset-password validates password confirmation matches', function () {
    $response = $this->postJson('/api/auth/reset-password', [
        'token' => 'some-token',
        'email' => 'user@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['password']);
});

it('reset-password fails with invalid email token', function () {
    User::factory()->create(['email' => 'user@example.com']);

    $response = $this->postJson('/api/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => 'user@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Invalid or expired reset token.',
    ]);
});

it('reset-password successfully resets via email token', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $oldPassword = $user->password;

    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'user@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Password has been reset successfully.',
    ]);

    $user->refresh();
    $this->assertNotEquals($oldPassword, $user->password);
    $this->assertTrue(Hash::check('newpassword123', $user->password));
});

it('reset-password requires email or phone', function () {
    $response = $this->postJson('/api/auth/reset-password', [
        'token' => 'some-token',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Either email or phone is required.',
    ]);
});

it('verify-otp requires phone and otp', function () {
    $response = $this->postJson('/api/auth/verify-otp', []);

    $response->assertStatus(422)->assertJsonValidationErrors(['phone', 'otp']);
});

it('verify-otp validates otp is 6 digits', function () {
    $response = $this->postJson('/api/auth/verify-otp', [
        'phone' => '01712345678',
        'otp' => '123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['otp']);
});

it('verify-otp fails with invalid otp', function () {
    $response = $this->postJson('/api/auth/verify-otp', [
        'phone' => '01712345678',
        'otp' => '000000',
    ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Invalid or expired OTP.',
    ]);
});

it('verify-otp fails with expired otp', function () {
    DB::table('password_reset_otps')->insert([
        'phone' => '01712345678',
        'otp' => '123456',
        'expires_at' => now()->subMinute(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/verify-otp', [
        'phone' => '01712345678',
        'otp' => '123456',
    ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Invalid or expired OTP.',
    ]);
});

it('verify-otp returns reset token for valid otp', function () {
    DB::table('password_reset_otps')->insert([
        'phone' => '01712345678',
        'otp' => '123456',
        'expires_at' => now()->addMinutes(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/verify-otp', [
        'phone' => '01712345678',
        'otp' => '123456',
    ]);

    $response->assertOk()->assertJsonStructure([
        'success',
        'data' => ['reset_token', 'phone'],
    ]);
});

it('reset-password successfully resets via phone token', function () {
    $user = User::factory()->create(['phone' => '01712345678']);
    $oldPassword = $user->password;

    DB::table('password_reset_otps')->insert([
        'phone' => '01712345678',
        'otp' => '123456',
        'reset_token' => 'valid-reset-token',
        'expires_at' => now()->addMinutes(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/reset-password', [
        'token' => 'valid-reset-token',
        'phone' => '01712345678',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Password has been reset successfully.',
    ]);

    $user->refresh();
    $this->assertNotEquals($oldPassword, $user->password);
    $this->assertTrue(Hash::check('newpassword123', $user->password));

    $this->assertDatabaseMissing('password_reset_otps', [
        'phone' => '01712345678',
    ]);
});

it('reset-password fails with invalid phone token', function () {
    User::factory()->create(['phone' => '01712345678']);

    $response = $this->postJson('/api/auth/reset-password', [
        'token' => 'invalid-token',
        'phone' => '01712345678',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Invalid or expired reset token.',
    ]);
});
