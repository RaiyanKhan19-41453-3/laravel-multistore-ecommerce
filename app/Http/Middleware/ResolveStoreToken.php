<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreToken
{
    public const COOKIE_NAME = 'store_token';

    /**
     * Bridge the HttpOnly storefront cookie into a Bearer credential so the
     * Sanctum guard and the manual bearer resolvers work unchanged.
     * An explicit Authorization header always takes precedence (API clients).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() && $request->cookie(self::COOKIE_NAME)) {
            $request->headers->set('Authorization', 'Bearer '.$request->cookie(self::COOKIE_NAME));
        }

        return $next($request);
    }
}
