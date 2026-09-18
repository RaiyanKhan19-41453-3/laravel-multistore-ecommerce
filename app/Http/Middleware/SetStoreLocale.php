<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetStoreLocale
{
    /**
     * Apply the store's locale/timezone per request so one codebase can
     * serve BD (en) or SA (ar) deployments. Never breaks when the
     * settings table does not exist yet (fresh install / tests).
     *
     * Multistore: prefers global settings (legacy behavior) then the
     * resolved store row, then config defaults.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            /** @var SettingsService $settings */
            $settings = app(SettingsService::class);
            $store = null;

            try {
                $store = app(CurrentStore::class)->get() ?? app(CurrentStore::class)->default();
            } catch (\Throwable) {
                $store = null;
            }

            $locale = $settings->get('store.locale')
                ?? $store?->locale
                ?? config('store.locale', config('app.locale', 'en'));
            $timezone = $settings->get('store.timezone')
                ?? $store?->timezone
                ?? config('store.timezone', config('app.timezone', 'UTC'));

            if (is_string($locale) && $locale !== '') {
                app()->setLocale($locale);
            }

            if (is_string($timezone) && $timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
                date_default_timezone_set($timezone);
            }
        } catch (\Throwable) {
            // Fresh installs without a settings table must still boot.
        }

        return $next($request);
    }
}
