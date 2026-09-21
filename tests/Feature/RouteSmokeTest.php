<?php

use Illuminate\Support\Facades\Route;

/**
 * Safety net: every GET endpoint must render without a server error.
 * Redirects (guest -> login), 403s (wrong role) and 404s (dummy ids)
 * are all acceptable: only 5xx responses fail. Catches crashed pages
 * like the old /products prop mismatch or empty-state 500s.
 */
it('serves every GET route without server errors for guests', function () {
    $failures = [];

    foreach (smokeUrls() as $url) {
        $status = $this->get($url)->getStatusCode();

        if ($status >= 500) {
            $failures[] = "GET {$url} -> {$status}";
        }
    }

    expect($failures)->toBeEmpty('Server errors: '.implode('; ', $failures));
});

it('serves every admin GET route without server errors for super admins', function () {
    $admin = createAdmin();
    $failures = [];

    foreach (smokeUrls() as $url) {
        if (! str_starts_with($url, '/admin')) {
            continue;
        }

        $status = $this->actingAs($admin)->get($url)->getStatusCode();

        if ($status >= 500) {
            $failures[] = "GET {$url} -> {$status}";
        }
    }

    expect($failures)->toBeEmpty();
});

function smokeUrls(): array
{
    $urls = [];

    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods())) {
            continue;
        }

        $uri = $route->uri();

        if (str_starts_with($uri, '_ignition') || str_starts_with($uri, 'sanctum/')) {
            continue;
        }

        // Optional params are dropped; required ones get a dummy value
        // that may 404 but must never 500.
        $uri = (string) preg_replace('/\/{[^}]*\?}/', '', $uri);
        $uri = str_replace('{slug}', 'definitely-missing-slug', $uri);
        $uri = (string) preg_replace('/\{[^}]+\}/', '1', $uri);

        $urls[] = '/'.ltrim($uri, '/');
    }

    return array_values(array_unique($urls));
}
