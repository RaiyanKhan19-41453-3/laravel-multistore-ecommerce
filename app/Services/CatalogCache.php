<?php

namespace App\Services;

use App\Models\HomepageSection;
use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CatalogCache
{
    public const FEATURED_TTL = 1800;

    public const TAXONOMY_TTL = 3600;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rememberFeatured(callable $callback): array
    {
        return Cache::remember(self::featuredKey(), self::FEATURED_TTL, $callback);
    }

    public static function rememberCategories(callable $callback): mixed
    {
        return Cache::remember(self::categoriesKey(), self::TAXONOMY_TTL, $callback);
    }

    public static function rememberBrands(callable $callback): mixed
    {
        return Cache::remember(self::brandsKey(), self::TAXONOMY_TTL, $callback);
    }

    public static function rememberPages(callable $callback): mixed
    {
        return Cache::remember(self::pagesKey(), self::TAXONOMY_TTL, $callback);
    }

    public static function rememberMenus(callable $callback): mixed
    {
        return Cache::remember(self::menusKey(), self::TAXONOMY_TTL, $callback);
    }

    public static function rememberHomepage(callable $callback): mixed
    {
        return Cache::remember(self::homepageKey(), self::TAXONOMY_TTL, $callback);
    }

    public static function rememberBlocks(callable $callback): mixed
    {
        return Cache::remember(self::blocksKey(), self::TAXONOMY_TTL, $callback);
    }

    public static function rememberHero(callable $callback): mixed
    {
        return Cache::remember(self::heroKey(), self::TAXONOMY_TTL, $callback);
    }

    public static function rememberBanners(callable $callback): mixed
    {
        return Cache::remember(self::bannersKey(), self::TAXONOMY_TTL, $callback);
    }

    public static function flushFeatured(): void
    {
        foreach (self::locales() as $locale) {
            Cache::forget(self::featuredKey($locale));
            // Legacy pre-multistore keys (same content, unprefixed).
            Cache::forget("storefront.featured.{$locale}");
        }
    }

    public static function flushCategories(): void
    {
        foreach (self::locales() as $locale) {
            Cache::forget(self::categoriesKey($locale));
            Cache::forget("storefront.categories.{$locale}");
        }
    }

    public static function flushBrands(): void
    {
        foreach (self::locales() as $locale) {
            Cache::forget(self::brandsKey($locale));
            Cache::forget("storefront.brands.{$locale}");
        }
    }

    public static function flushPages(): void
    {
        foreach (self::locales() as $locale) {
            Cache::forget(self::pagesKey($locale));
            Cache::forget("storefront.pages.{$locale}");
        }
    }

    public static function flushMenus(): void
    {
        foreach (self::locales() as $locale) {
            Cache::forget(self::menusKey($locale));
        }
    }

    public static function flushHomepage(): void
    {
        foreach (self::locales() as $locale) {
            Cache::forget(self::homepageKey($locale));
            Cache::forget(self::blocksKey($locale));
            Cache::forget(self::heroKey($locale));
            Cache::forget(self::bannersKey($locale));
        }
    }

    public static function flushSitemap(): void
    {
        foreach (self::stores() as $storeId) {
            foreach (self::locales() as $locale) {
                Cache::forget("sitemap:{$storeId}:{$locale}");
            }
        }
    }

    public static function featuredKey(?string $locale = null): string
    {
        return self::prefix().'storefront.featured.'.($locale ?? app()->getLocale());
    }

    public static function categoriesKey(?string $locale = null): string
    {
        return self::prefix().'storefront.categories.'.($locale ?? app()->getLocale());
    }

    public static function brandsKey(?string $locale = null): string
    {
        return self::prefix().'storefront.brands.'.($locale ?? app()->getLocale());
    }

    public static function pagesKey(?string $locale = null): string
    {
        return self::prefix().'storefront.pages.'.($locale ?? app()->getLocale());
    }

    public static function menusKey(?string $locale = null): string
    {
        return self::prefix().'storefront.menus.'.($locale ?? app()->getLocale());
    }

    public static function homepageKey(?string $locale = null): string
    {
        // Versioned by the known section set so newly added section
        // types never hide behind a stale cached order.
        $version = substr(md5(implode(',', HomepageSection::KEYS)), 0, 8);

        return self::prefix()."storefront.homepage.{$version}.".($locale ?? app()->getLocale());
    }

    public static function blocksKey(?string $locale = null): string
    {
        $version = substr(md5(implode(',', HomepageSection::KEYS)), 0, 8);

        return self::prefix()."storefront.blocks.{$version}.".($locale ?? app()->getLocale());
    }

    public static function heroKey(?string $locale = null): string
    {
        return self::prefix().'storefront.hero.'.($locale ?? app()->getLocale());
    }

    public static function bannersKey(?string $locale = null): string
    {
        return self::prefix().'storefront.banners.'.($locale ?? app()->getLocale());
    }

    private static function prefix(): string
    {
        try {
            $id = app(CurrentStore::class)->id()
                ?? app(CurrentStore::class)->default()?->id;
        } catch (\Throwable) {
            return '';
        }

        return $id ? "store:{$id}:" : '';
    }

    /**
     * @return array<int, string>
     */
    private static function locales(): array
    {
        return ['en', 'ar'];
    }

    /**
     * @return array<int, string>
     */
    private static function stores(): array
    {
        $ids = ['global'];

        try {
            if (Schema::hasTable('stores')) {
                foreach (Store::query()->pluck('id') as $id) {
                    $ids[] = (string) $id;
                }
            }
        } catch (\Throwable) {
            // Self-healing on next boot; never break the write path.
        }

        return $ids;
    }
}
