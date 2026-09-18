<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\AdminStoreContext;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAdminStore
{
    /**
     * For admin requests, point the shared store context at the merchant
     * selected in session (or via explicit header). Without a valid
     * selection the platform-wide view applies and nothing changes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $next($request);
            }

            $context = app(AdminStoreContext::class);
            $context->clearOverride();

            $headerSlug = $request->header('X-Store-Slug');
            $headerId = $request->header('X-Store-Id');

            if ((is_string($headerSlug) && $headerSlug !== '') || is_numeric($headerId)) {
                $store = is_numeric($headerId) && (int) $headerId > 0
                    ? Store::find((int) $headerId)
                    : Store::where('slug', $headerSlug)->first();

                if ($store && $context->canAccess($user, $store)) {
                    $context->setOverride($store);
                    app(CurrentStore::class)->set($store);
                    $request->attributes->set('store', $store);
                    $request->attributes->set('store_id', $store->id);

                    return $next($request);
                }

                // An explicit header for an inaccessible store must not leave
                // the storefront-resolved CurrentStore in place: fall through
                // to the session selection so listings and creations stay on
                // a store the user may actually manage.
            }

            $selected = $context->selected($user);

            if ($selected) {
                app(CurrentStore::class)->set($selected);
                $request->attributes->set('store', $selected);
                $request->attributes->set('store_id', $selected->id);
            } else {
                // Platform-wide view: clear any store that ResolveStore
                // resolved from headers/host so the BelongsToStore global
                // scope doesn't auto-filter admin listings.
                app(CurrentStore::class)->forget();
            }
        } catch (\Throwable) {
            // Admin must never break because of store resolution.
        }

        return $next($request);
    }

    /**
     * Clear the store context after the response is sent so the singleton
     * does not leak into subsequent requests (tests, Octane workers).
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            app(CurrentStore::class)->forget();
            app(AdminStoreContext::class)->clearOverride();
        } catch (\Throwable) {
            //
        }
    }
}
