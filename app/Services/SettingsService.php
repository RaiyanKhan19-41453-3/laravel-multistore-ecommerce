<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    public const CACHE_KEY = 'store.settings.v1';

    /**
     * Country presets any client can start from. Admin UI applies one
     * with a single click, then tweaks individual keys.
     *
     * @return array<string, array<string, string>>
     */
    public static function presets(): array
    {
        return [
            'BD' => [
                'store.country' => 'BD',
                'store.currency' => 'BDT',
                'store.locale' => 'en',
                'store.timezone' => 'Asia/Dhaka',
                'tax.mode' => 'off',
                'tax.rate' => '0',
                'payment.cod_enabled' => '1',
                'payment.sslcommerz_enabled' => '1',
                'payment.bkash_enabled' => '0',
                'payment.moyasar_enabled' => '0',
                'payment.tabby_enabled' => '0',
                'payment.stripe_enabled' => '0',
            ],
            'SA' => [
                'store.country' => 'SA',
                'store.currency' => 'SAR',
                'store.locale' => 'ar',
                'store.timezone' => 'Asia/Riyadh',
                'tax.mode' => 'vat',
                'tax.rate' => '15',
                'payment.cod_enabled' => '1',
                'payment.moyasar_enabled' => '1',
                'payment.tabby_enabled' => '1',
                'payment.stripe_enabled' => '1',
                'payment.sslcommerz_enabled' => '0',
                'payment.bkash_enabled' => '0',
            ],
        ];
    }

    /**
     * Merged settings for a store: global defaults (NULL store_id) with
     * the store's own rows overriding. A null store id resolves to the
     * current request store, which may itself be null for global scope
     * (console, seeders, store-less tests).
     *
     * @return array<string, string|null>
     */
    public function all(?int $storeId = null): array
    {
        $storeId ??= $this->currentStoreId();

        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        return Cache::rememberForever($this->cacheKey($storeId), function () use ($storeId): array {
            $global = Setting::query()->whereNull('store_id')->pluck('value', 'key')->all();

            if ($storeId === null) {
                return $global;
            }

            $overrides = Setting::query()->where('store_id', $storeId)->pluck('value', 'key')->all();

            return array_merge($global, $overrides);
        });
    }

    public function get(string $key, ?string $default = null, ?int $storeId = null): ?string
    {
        $all = $this->all($storeId ?? $this->currentStoreId());

        return $all[$key] ?? $default;
    }

    public function set(string $key, ?string $value, string $group = 'general', ?int $storeId = null): void
    {
        $storeId ??= $this->currentStoreId();

        Setting::updateOrCreate(
            ['store_id' => $storeId, 'key' => $key],
            ['value' => $value, 'group' => $group]
        );

        $this->forgetCache($storeId);
    }

    /**
     * @param  array<string, ?string>  $values
     */
    public function setMany(array $values, string $group = 'general', ?int $storeId = null): void
    {
        $storeId ??= $this->currentStoreId();

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['store_id' => $storeId, 'key' => $key],
                ['value' => $value, 'group' => $group]
            );
        }

        $this->forgetCache($storeId);
    }

    public function applyPreset(string $country, ?int $storeId = null): void
    {
        $preset = self::presets()[strtoupper($country)] ?? null;

        if (! $preset) {
            throw new \InvalidArgumentException("Unknown country preset '{$country}'.");
        }

        $this->setMany($preset, 'store', $storeId ?? $this->currentStoreId());
    }

    public function forgetCache(?int $storeId = null): void
    {
        Cache::forget(self::CACHE_KEY);

        if ($storeId !== null) {
            Cache::forget($this->cacheKey($storeId));

            return;
        }

        // A global write changes every store's merged view, so flush all
        // per-store keys too. Global writes are rare (seeders, console).
        try {
            if (Schema::hasTable('stores')) {
                foreach (Store::query()->pluck('id') as $id) {
                    Cache::forget($this->cacheKey((int) $id));
                }
            }
        } catch (\Throwable) {
            // Cache staleness is self-healing on next deploy/test boot.
        }
    }

    private function cacheKey(?int $storeId): string
    {
        return $storeId === null ? self::CACHE_KEY : "store.{$storeId}.settings.v1";
    }

    private function currentStoreId(): ?int
    {
        try {
            return app(CurrentStore::class)->scopeId();
        } catch (\Throwable) {
            return null;
        }
    }
}
