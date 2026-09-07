<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
    Http::fake();
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

    $stored = DB::table('password_reset_otps')->where('phone', '01712345678')->first();
    expect($stored->otp)->not->toBeEmpty();
    expect(Hash::check('123456', $stored->otp))->toBeFalse();
});

it('forgot-password returns 503 when sms send fails', function () {
    Http::fake([
        '*' => Http::response('Service Unavailable', 503),
    ]);
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

    $response->assertStatus(503)->assertJson([
        'success' => false,
    ]);

    $this->assertDatabaseMissing('password_reset_otps', [
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
        'otp' => '123456',
    ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Invalid or expired OTP.',
    ]);
});

it('verify-otp fails with expired otp', function () {
    DB::table('password_reset_otps')->insert([
        'phone' => '01712345678',
        'otp' => Hash::make('123456'),
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
        'otp' => Hash::make('123456'),
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

it('verify-otp burns the otp so it cannot be reused', function () {
    DB::table('password_reset_otps')->insert([
        'phone' => '01712345678',
        'otp' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson('/api/auth/verify-otp', [
        'phone' => '01712345678',
        'otp' => '123456',
    ])->assertOk();

    $response = $this->postJson('/api/auth/verify-otp', [
        'phone' => '01712345678',
        'otp' => '123456',
    ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'This code was already verified. Use the reset link or request a new code.',
    ]);
});

it('verify-otp locks out after too many wrong attempts', function () {
    DB::table('password_reset_otps')->insert([
        'phone' => '01712345678',
        'otp' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/verify-otp', [
            'phone' => '01712345678',
            'otp' => '000000',
        ])->assertStatus(422);
    }

    $response = $this->postJson('/api/auth/verify-otp', [
        'phone' => '01712345678',
        'otp' => '123456',
    ]);

    $response->assertStatus(429)->assertJson([
        'success' => false,
    ]);
});

it('reset-password successfully resets via phone token', function () {
    $user = User::factory()->create(['phone' => '01712345678']);
    $oldPassword = $user->password;

    DB::table('password_reset_otps')->insert([
        'phone' => '01712345678',
        'otp' => Hash::make('123456'),
        'reset_token' => hash('sha256', 'valid-reset-token'),
        'reset_expires_at' => now()->addMinutes(5),
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

it('reset-password revokes existing tokens', function () {
    $user = User::factory()->create(['phone' => '01712345678']);
    $user->createToken('old-token');
    expect($user->tokens()->count())->toBe(1);

    DB::table('password_reset_otps')->insert([
        'phone' => '01712345678',
        'otp' => Hash::make('123456'),
        'reset_token' => hash('sha256', 'revoke-test-token'),
        'reset_expires_at' => now()->addMinutes(5),
        'expires_at' => now()->addMinutes(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson('/api/auth/reset-password', [
        'token' => 'revoke-test-token',
        'phone' => '01712345678',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertOk();

    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('login sets an HttpOnly store token cookie', function () {
    User::factory()->create(['email' => 'cookie@example.com']);

    $response = $this->postJson('/api/auth/login', [
        'identifier' => 'cookie@example.com',
        'password' => 'password',
    ]);

    $response->assertOk();
    $response->assertCookie('store_token');

    $cookie = $response->getCookie('store_token', false);
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($cookie->getSameSite())->toBe('lax');
});

it('register sets an HttpOnly store token cookie', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Cookie User',
        'email' => 'cookiereg@example.com',
        'phone' => '01711111111',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertOk();
    $response->assertCookie('store_token');
    expect($response->getCookie('store_token', false)->isHttpOnly())->toBeTrue();
});

it('authenticates api requests via store token cookie', function () {
    $user = User::factory()->create(['email' => 'cookieauth@example.com']);

    $login = $this->postJson('/api/auth/login', [
        'identifier' => 'cookieauth@example.com',
        'password' => 'password',
    ])->assertOk();

    $token = $login->json('data.token');
    expect($token)->not->toBeEmpty();

    $this->withUnencryptedCookie('store_token', $token)
        ->withCredentials()
        ->postJson('/api/auth/logout')
        ->assertOk()
        ->assertCookieExpired('store_token');

    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('prefers bearer header over store token cookie', function () {
    $userA = User::factory()->create(['email' => 'cookiea@example.com']);
    $userB = User::factory()->create(['email' => 'cookieb@example.com']);

    $tokenA = $userA->createToken('a')->plainTextToken;
    $tokenB = $userB->createToken('b')->plainTextToken;

    $response = $this->withUnencryptedCookie('store_token', $tokenA)
        ->withCredentials()
        ->withHeader('Authorization', "Bearer {$tokenB}")
        ->postJson('/api/auth/logout');

    $response->assertOk();

    expect($userA->fresh()->tokens()->count())->toBe(1);
    expect($userB->fresh()->tokens()->count())->toBe(0);
});

it('logout-all revokes every token', function () {
    $user = User::factory()->create(['email' => 'logoutall@example.com']);
    $user->createToken('other-device');

    $login = $this->postJson('/api/auth/login', [
        'identifier' => 'logoutall@example.com',
        'password' => 'password',
    ])->assertOk();

    expect($user->fresh()->tokens()->count())->toBe(2);

    $this->withHeader('Authorization', 'Bearer '.$login->json('data.token'))
        ->postJson('/api/auth/logout-all')
        ->assertOk()
        ->assertCookieExpired('store_token');

    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('register normalizes email and rejects duplicate phones', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Case User',
        'email' => 'CASEDUP@Example.COM',
        'phone' => '01712345678',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertOk();

    $this->assertDatabaseHas('users', ['email' => 'casedup@example.com']);

    $this->postJson('/api/auth/login', [
        'identifier' => 'casedup@example.com',
        'password' => 'password123',
    ])->assertOk();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Second User',
        'email' => 'second@example.com',
        'phone' => '+8801712345678',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
});

it('login accepts international phone format', function () {
    User::factory()->create(['email' => 'intlphone@example.com', 'phone' => '01712345678']);

    $this->postJson('/api/auth/login', [
        'identifier' => '+8801712345678',
        'password' => 'password',
    ])->assertOk();
});

it('verify-otp rejects already-verified codes without burning the token', function () {
    DB::table('password_reset_otps')->insert([
        'phone' => '01712345678',
        'otp' => Hash::make('123456'),
        'reset_token' => hash('sha256', 'issued-token'),
        'reset_expires_at' => now()->addMinutes(30),
        'expires_at' => now()->addMinutes(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/auth/verify-otp', [
            'phone' => '01712345678',
            'otp' => '000000',
        ])->assertStatus(422);
    }

    $this->assertDatabaseHas('password_reset_otps', ['phone' => '01712345678']);

    $response = $this->postJson('/api/auth/reset-password', [
        'token' => 'issued-token',
        'phone' => '01712345678',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'User not found.',
    ]);
});

it('phone reset clears pending email reset tokens and vice versa', function () {
    $user = User::factory()->create(['email' => 'both@example.com', 'phone' => '01712345678']);

    DB::table('password_reset_tokens')->insert([
        'email' => 'both@example.com',
        'token' => Hash::make('email-token'),
        'created_at' => now(),
    ]);
    DB::table('password_reset_otps')->insert([
        'phone' => '01712345678',
        'otp' => Hash::make('123456'),
        'reset_token' => hash('sha256', 'phone-token'),
        'reset_expires_at' => now()->addMinutes(30),
        'expires_at' => now()->addMinutes(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson('/api/auth/reset-password', [
        'token' => 'phone-token',
        'phone' => '01712345678',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertOk();

    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'both@example.com']);
    $this->assertDatabaseMissing('password_reset_otps', ['phone' => '01712345678']);
    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('forgot-password does not resend sms within cooldown window', function () {
    Http::fake();
    User::factory()->create(['phone' => '01712345678']);

    config(['sms.enabled' => ['twilio' => true]]);
    config(['sms.gateways.twilio' => [
        'account_sid' => 'AC123',
        'auth_token' => 'token',
        'from_number' => '+1234567890',
    ]]);

    $this->postJson('/api/auth/forgot-password', [
        'identifier' => '01712345678',
    ])->assertOk();

    Http::assertSentCount(1);

    $this->postJson('/api/auth/forgot-password', [
        'identifier' => '01712345678',
    ])->assertOk();

    Http::assertSentCount(1);
});
