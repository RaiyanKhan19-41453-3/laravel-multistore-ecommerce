<?php

namespace App\Http\Middleware;

use App\Support\AdminStoreContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureAdminStoreAccess
{
    /**
     * Reject route-model-bound records from outside the selected store.
     * Runs after ResolveAdminStore; a no-op in the platform-wide view.
     * Child records (images, variants, coupons) are additionally guarded
     * by parent-ownership checks in their controllers, so binding the
     * parent transitively protects them.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $selectedId = app(AdminStoreContext::class)->selectedId($request->user());

            if ($selectedId === null) {
                return $next($request);
            }

            foreach ($request->route()?->parameters() ?? [] as $parameter) {
                if (! $parameter instanceof Model) {
                    continue;
                }

                $attributes = $parameter->getAttributes();

                if (! array_key_exists('store_id', $attributes)) {
                    continue;
                }

                // Integer comparison: PDO may return numeric strings.
                // A NULL (legacy) store never matches an explicit selection.
                if ((int) $parameter->getAttribute('store_id') !== $selectedId) {
                    abort(404);
                }
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            // Admin must never break because of store resolution.
        }

        return $next($request);
    }
}
