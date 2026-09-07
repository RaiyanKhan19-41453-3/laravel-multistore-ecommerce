<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CartService;
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
        $bearerUser = $this->resolveBearerUser($request);

        if ($bearerUser) {
            $cart = $this->cartService->getOrCreateForUser($bearerUser);
        } elseif ($request->hasHeader('X-Guest-Token')) {
            $guestToken = $request->header('X-Guest-Token');
            $cart = $this->cartService->getOrCreateForGuest($guestToken);
        } elseif ($request->user()) {
            $cart = $this->cartService->getOrCreateForUser($request->user());
        } elseif ($request->cookie('store_guest_token')) {
            $guestToken = $request->cookie('store_guest_token');
            $cart = $this->cartService->getOrCreateForGuest($guestToken);
        } else {
            $guestToken = Str::uuid()->toString();
            $cart = $this->cartService->getOrCreateForGuest($guestToken);
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
