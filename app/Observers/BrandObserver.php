<?php

namespace App\Observers;

use App\Models\Brand;
use App\Services\CatalogCache;

class BrandObserver
{
    public function saved(Brand $brand): void
    {
        CatalogCache::flushBrands();
        CatalogCache::flushSitemap();
    }

    public function deleted(Brand $brand): void
    {
        CatalogCache::flushBrands();
        CatalogCache::flushSitemap();
    }

    public function restored(Brand $brand): void
    {
        CatalogCache::flushBrands();
        CatalogCache::flushSitemap();
    }
}
