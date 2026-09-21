<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\CategoryController;
use App\Services\CurrencyService;
use App\Services\MenuService;
use App\Services\SettingsService;
use App\Support\AdminStoreContext;
use App\Support\CurrentStore;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user() && ($request->is('admin/*') || $request->is('settings/*'))
                    ? $request->user()->getAllPermissions()->pluck('name')->all()
                    : [],
            ],
            'locale' => app()->getLocale(),
            'direction' => $this->direction(),
            'store' => $this->storeData(),
            'adminStore' => $this->adminStoreData($request),
            'nav' => $request->is('admin*') ? ['menus' => [], 'categories' => []] : $this->storefrontNav(),
        ]);
    }

    private function direction(): string
    {
        return in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
    }

    /**
     * @return array<string, string>
     */
    private function storeData(): array
    {
        try {
            /** @var SettingsService $settings */
            $settings = app(SettingsService::class);
            /** @var CurrencyService $currency */
            $currency = app(CurrencyService::class);

            $store = null;

            try {
                $store = app(CurrentStore::class)->get() ?? app(CurrentStore::class)->default();
            } catch (\Throwable) {
                $store = null;
            }

            return [
                'id' => (string) ($store?->id ?? ''),
                'slug' => (string) ($store?->slug ?? ''),
                'name' => (string) ($settings->get('store.name') ?? $store?->name ?? config('store.name', 'My Store')),
                'tagline' => (string) ($settings->get('store.tagline') ?? ''),
                'logo' => (string) ($settings->get('store.logo') ?? ''),
                'show_store_name' => $settings->get('store.show_store_name', '1') !== '0',
                'email' => (string) ($settings->get('store.email') ?? ''),
                'phone' => (string) ($settings->get('store.phone') ?? ''),
                'address' => (string) ($settings->get('store.address') ?? ''),
                'city' => (string) ($settings->get('store.city') ?? ''),
                'country' => (string) ($settings->get('store.country') ?? $store?->country ?? config('store.country', 'BD')),
                'currency' => $currency->code(),
                'currencySymbol' => $currency->symbol(),
                'locale' => app()->getLocale(),
            ];
        } catch (\Throwable) {
            return [
                'name' => (string) config('store.name', 'My Store'),
                'show_store_name' => true,
                'country' => (string) config('store.country', 'BD'),
                'currency' => (string) config('store.currency', 'BDT'),
                'currencySymbol' => '৳',
                'locale' => app()->getLocale(),
            ];
        }
    }

    /**
     * Header navigation for storefront pages, shared so first paint is
     * complete instead of fetching menus after load. Skipped for admin
     * routes at the call site.
     *
     * @return array{menus: array<int, mixed>, categories: array<int, mixed>}
     */
    private function storefrontNav(): array
    {
        try {
            $menus = app(MenuService::class)->tree();
        } catch (\Throwable) {
            $menus = [];
        }

        try {
            $response = app(CategoryController::class)->index();
            /** @var array<string, mixed> $decoded */
            $decoded = $response->getData(true);
            $categories = array_slice($decoded['data'] ?? [], 0, 10);
        } catch (\Throwable) {
            $categories = [];
        }

        return ['menus' => $menus, 'categories' => $categories];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function adminStoreData(Request $request): ?array
    {
        try {
            if (! $request->is('admin/*') || ! $request->user()) {
                return null;
            }

            $context = app(AdminStoreContext::class);
            $user = $request->user();

            return [
                'selected_id' => $context->selectedId($user),
                'stores' => $context->availableStores($user)->map(fn ($store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                ])->all(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
