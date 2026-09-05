<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsGateways\SmsGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
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
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = $validated['identifier'];

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $identifier)->first();
        } else {
            $user = User::where('phone', $identifier)->first();
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
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
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

        $identifier = $validated['identifier'];

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $this->sendEmailResetLink($identifier);
        }

        return $this->sendPhoneOtp($identifier);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|size:6',
        ]);

        $phone = $validated['phone'];

        $record = DB::table('password_reset_otps')
            ->where('phone', $phone)
            ->where('otp', $validated['otp'])
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        $resetToken = Str::random(64);

        DB::table('password_reset_otps')
            ->where('phone', $phone)
            ->update(['reset_token' => $resetToken]);

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
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $email = $request->input('email');
        $phone = $request->input('phone');

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

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiryMinutes = config('sms.otp.expiry_minutes', 10);

        DB::table('password_reset_otps')->updateOrInsert(
            ['phone' => $phone],
            [
                'otp' => $otp,
                'reset_token' => null,
                'expires_at' => now()->addMinutes($expiryMinutes),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $sms->send($phone, "Your verification code is: {$otp}. It expires in {$expiryMinutes} minutes.");

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
            ->where('reset_token', $validated['token'])
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
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

        DB::table('password_reset_otps')->where('phone', $phone)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully.',
        ]);
    }
}
