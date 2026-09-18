<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CartService;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ResolveCart
{
    public function __construct(protected CartService $cartService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $cart = null;
        $storeId = app(CurrentStore::class)->scopeId();
        $bearerUser = $this->resolveBearerUser($request);

        if ($bearerUser) {
            $cart = $this->cartService->getOrCreateForUser($bearerUser, $storeId);
        } elseif ($request->hasHeader('X-Guest-Token')) {
            $guestToken = $request->header('X-Guest-Token');

            if (! $this->validGuestToken($guestToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid guest token.',
                ], 400);
            }

            $cart = $this->cartService->getOrCreateForGuest($guestToken, $storeId);
        } elseif ($request->user()) {
            $cart = $this->cartService->getOrCreateForUser($request->user(), $storeId);
        } elseif ($request->cookie('store_guest_token')) {
            $guestToken = $request->cookie('store_guest_token');

            if (! $this->validGuestToken($guestToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid guest token.',
                ], 400);
            }

            $cart = $this->cartService->getOrCreateForGuest($guestToken, $storeId);
        } else {
            $guestToken = Str::uuid()->toString();
            $cart = $this->cartService->getOrCreateForGuest($guestToken, $storeId);
        }

        $request->attributes->set('cart', $cart);

        $response = $next($request);

        if ($response instanceof Response && ! $bearerUser && ! $request->user() && ! $request->hasHeader('X-Guest-Token') && ! $request->cookie('store_guest_token') && isset($guestToken)) {
            $response->headers->setCookie(
                cookie('store_guest_token', $guestToken, 60 * 24 * 30, '/', null, $request->secure(), true)
            );
        }

        return $response;
    }

    /**
     * Guest tokens go straight into a varchar(36) lookup: reject anything
     * that cannot fit instead of 500ing on insert. Length-checked only
     * (not UUID-strict) so existing client tokens keep working.
     */
    private function validGuestToken(mixed $token): bool
    {
        return is_string($token) && $token !== '' && strlen($token) <= 36;
    }

    private function resolveBearerUser(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        $user = $accessToken->tokenable;

        return $user instanceof User ? $user : null;
    }
}
