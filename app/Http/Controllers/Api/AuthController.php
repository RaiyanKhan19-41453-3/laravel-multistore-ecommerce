<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PhoneHelper;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveStoreToken;
use App\Models\User;
use App\Services\SmsGateways\SmsGatewayFactory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    private const MAX_OTP_ATTEMPTS = 5;

    private const RESET_TOKEN_TTL_MINUTES = 30;

    /**
     * HttpOnly cookie carrying the storefront bearer token. The token stays
     * in the JSON body as well for native/API clients that use the
     * Authorization header directly.
     */
    private function storeTokenCookie(Request $request, string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(
            ResolveStoreToken::COOKIE_NAME,
            $token,
            config('sanctum.expiration', 60 * 24 * 7),
            '/',
            null,
            $this->cookieSecure($request),
            true,
            false,
            'Lax',
        );
    }

    /**
     * Mirror the store-token cookie flags so browsers reliably evict it,
     * including the Secure flag set at login time on HTTPS.
     */
    private function forgetStoreTokenCookie(Request $request): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(
            ResolveStoreToken::COOKIE_NAME,
            '',
            -2628000,
            '/',
            null,
            $this->cookieSecure($request),
            true,
            false,
            'Lax',
        );
    }

    private function cookieSecure(Request $request): bool
    {
        return $request->secure() || (bool) config('session.secure', false);
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => strtolower(trim($request->input('email', ''))),
            'phone' => PhoneHelper::normalize($request->input('phone')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'token' => $token,
            ],
        ])->withCookie($this->storeTokenCookie($request, $token));
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = trim($validated['identifier']);

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', strtolower($identifier))->first();
        } else {
            $user = User::where('phone', PhoneHelper::normalize($identifier))->first();
        }

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'token' => $token,
            ],
        ])->withCookie($this->storeTokenCookie($request, $token));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ])->withCookie($this->forgetStoreTokenCookie($request));
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully.',
        ])->withCookie($this->forgetStoreTokenCookie($request));
    }

    public function checkEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));
        $exists = User::where('email', $normalizedEmail)->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'exists' => $exists,
            ],
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string|max:255',
        ]);

        $identifier = trim($validated['identifier']);

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $this->sendEmailResetLink($identifier);
        }

        return $this->sendPhoneOtp(PhoneHelper::normalize($identifier) ?? $identifier);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|size:6',
        ]);

        $phone = PhoneHelper::normalize($validated['phone']) ?? $validated['phone'];

        $record = DB::table('password_reset_otps')
            ->where('phone', $phone)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        if (! empty($record->reset_token)) {
            return response()->json([
                'success' => false,
                'message' => 'This code was already verified. Use the reset link or request a new code.',
            ], 422);
        }

        if ($record->attempts >= self::MAX_OTP_ATTEMPTS) {
            DB::table('password_reset_otps')->where('phone', $phone)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please request a new code.',
            ], 429);
        }

        if (! Hash::check($validated['otp'], $record->otp)) {
            DB::table('password_reset_otps')->where('phone', $phone)->increment('attempts');

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        $resetToken = Str::random(64);

        DB::table('password_reset_otps')
            ->where('phone', $phone)
            ->update([
                'otp' => Hash::make(Str::random(32)),
                'attempts' => 0,
                'reset_token' => hash('sha256', $resetToken),
                'reset_expires_at' => now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'reset_token' => $resetToken,
                'phone' => $phone,
            ],
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $email = $validated['email'] ?? null;
        $phone = isset($validated['phone']) ? PhoneHelper::normalize($validated['phone']) : null;

        if ($email) {
            return $this->resetViaEmailToken($validated, $email);
        }

        if ($phone) {
            return $this->resetViaPhoneToken($validated, $phone);
        }

        return response()->json([
            'success' => false,
            'message' => 'Either email or phone is required.',
        ], 422);
    }

    private function sendEmailResetLink(string $email): JsonResponse
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists with that identifier, a reset link has been sent.',
            ]);
        }

        $status = Password::broker()->sendResetLink([
            'email' => $email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists with that identifier, a reset link has been sent.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to send reset link. Please try again later.',
        ], 500);
    }

    private function sendPhoneOtp(string $phone): JsonResponse
    {
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists with that identifier, a reset link has been sent.',
            ]);
        }

        $sms = SmsGatewayFactory::getDefault();

        if (! $sms) {
            return response()->json([
                'success' => false,
                'message' => 'SMS service is not configured.',
            ], 500);
        }

        $existing = DB::table('password_reset_otps')->where('phone', $phone)->first();

        if ($existing
            && Carbon::parse($existing->expires_at)->isFuture()
            && Carbon::parse($existing->updated_at)->greaterThan(now()->subMinute())) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists with that identifier, a reset link has been sent.',
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiryMinutes = config('sms.otp.expiry_minutes', 10);

        DB::table('password_reset_otps')->updateOrInsert(
            ['phone' => $phone],
            [
                'otp' => Hash::make($otp),
                'attempts' => 0,
                'reset_token' => null,
                'reset_expires_at' => null,
                'expires_at' => now()->addMinutes($expiryMinutes),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        try {
            $sent = $sms->send($phone, "Your verification code is: {$otp}. It expires in {$expiryMinutes} minutes.");
        } catch (\Exception $e) {
            $sent = false;
        }

        if (! $sent) {
            DB::table('password_reset_otps')->where('phone', $phone)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Unable to send verification code. Please try again later.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'If an account exists with that identifier, a reset link has been sent.',
        ]);
    }

    private function resetViaEmailToken(array $validated, string $email): JsonResponse
    {
        $status = Password::broker()->reset([
            'email' => strtolower(trim($email)),
            'password' => $validated['password'],
            'password_confirmation' => request()->input('password_confirmation'),
            'token' => $validated['token'],
        ], function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            $user->tokens()->delete();

            if ($user->phone) {
                DB::table('password_reset_otps')->where('phone', $user->phone)->delete();
            }
        });

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired reset token.',
        ], 422);
    }

    private function resetViaPhoneToken(array $validated, string $phone): JsonResponse
    {
        $record = DB::table('password_reset_otps')
            ->where('phone', $phone)
            ->whereNotNull('reset_token')
            ->where('reset_expires_at', '>', now())
            ->first();

        if (! $record || ! hash_equals($record->reset_token, hash('sha256', $validated['token']))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 422);
        }

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('password_reset_otps')->where('phone', $phone)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully.',
        ]);
    }
}
