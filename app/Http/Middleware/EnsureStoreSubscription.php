<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\AdminStoreContext;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreSubscription
{
    /**
     * Suspend storefront and merchant-admin access for stores past their
     * entitlement. Inert unless platform billing enforcement is enabled,
     * so installs and tests keep working until the platform opts in.
     * Super-admins always pass; auth, billing, and signup routes are
     * exempt so merchants can log in and subscribe.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! config('platform.billing.enforced', false)) {
                return $next($request);
            }

            if ($this->isExempt($request)) {
                return $next($request);
            }

            $user = $request->user();

            if ($user && $this->isSuperAdmin($user)) {
                return $next($request);
            }

            $store = $this->resolveStore($request);

            if ($store && ! $store->isBillingEntitled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This store subscription has expired.',
                ], 402);
            }
        } catch (\Throwable) {
            // Billing must never take down the storefront or admin.
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        return $request->is(
            'up',
            'admin/login*',
            'admin/billing*',
            'admin/store-context*',
            'api/auth/*',
            'api/stores*',
            // Gateway and courier callbacks carry their own identity and
            // must never be gated on the resolved (often default) store:
            // blocking them would silently break money and fulfillment
            // for every store over one store's billing state.
            'api/payments/webhook*',
            'api/payments/callback*',
            'api/webhooks/*'
        );
    }

    private function isSuperAdmin($user): bool
    {
        try {
            return $user->hasRole('super-admin');
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveStore(Request $request): ?Store
    {
        try {
            if ($request->is('admin/*')) {
                return app(AdminStoreContext::class)->selected($request->user());
            }

            return app(CurrentStore::class)->get() ?? app(CurrentStore::class)->default();
        } catch (\Throwable) {
            return null;
        }
    }
}
