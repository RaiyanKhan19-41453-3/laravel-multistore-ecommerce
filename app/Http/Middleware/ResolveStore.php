<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ResolveStore
{
    /**
     * Resolve the current store per request. Never aborts: unknown or
     * missing identifiers fall back to the default store so the existing
     * single-store install keeps working unchanged.
     *
     * Order: explicit header > ?store= > custom domain > subdomain > default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $store = $this->resolve($request);
        } catch (\Throwable) {
            $store = null;
        }

        try {
            app(CurrentStore::class)->set($store);
        } catch (\Throwable) {
            // Container not ready in some test boots; request still proceeds.
        }

        if ($store) {
            $request->attributes->set('store', $store);
            $request->attributes->set('store_id', $store->id);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?Store
    {
        try {
            if (! Schema::hasTable('stores')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        // 1. Explicit API overrides.
        $headerSlug = $request->header('X-Store-Slug');
        $headerId = $request->header('X-Store-Id');

        if (is_string($headerSlug) && $headerSlug !== '') {
            $found = Store::where('slug', $headerSlug)->first();

            if ($found) {
                return $found;
            }
        }

        if (is_string($headerId) || is_numeric($headerId)) {
            if (is_numeric($headerId) && (int) $headerId > 0) {
                $found = Store::find((int) $headerId);

                if ($found) {
                    return $found;
                }
            }
        }

        // 2. Query param (?store=slug) for shareable preview links.
        $query = $request->query('store');

        if (is_string($query) && $query !== '') {
            $found = Store::where('slug', $query)->first();

            if ($found) {
                return $found;
            }
        }

        $host = strtolower((string) $request->getHost());

        if ($host !== '') {
            // 3. Custom domain match (future; column exists from day one).
            $byDomain = Store::where('domain', $host)->first();

            if ($byDomain) {
                return $byDomain;
            }

            // 4. Subdomain match: {slug}.platform.tld (skip www / bare domain).
            $parts = explode('.', $host);

            if (count($parts) >= 3 && $parts[0] !== 'www' && $parts[0] !== '') {
                $bySlug = Store::where('slug', $parts[0])->first();

                if ($bySlug) {
                    return $bySlug;
                }
            }
        }

        // 5. Fallback: default (first) store keeps single-store behavior.
        return Store::default();
    }
}
