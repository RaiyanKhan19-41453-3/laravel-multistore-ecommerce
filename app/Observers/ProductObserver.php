<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\CatalogCache;

class ProductObserver
{
    public function saved(Product $product): void
    {
        CatalogCache::flushFeatured();
        CatalogCache::flushSitemap();
    }

    public function deleted(Product $product): void
    {
        CatalogCache::flushFeatured();
        CatalogCache::flushSitemap();
    }

    public function restored(Product $product): void
    {
        CatalogCache::flushFeatured();
        CatalogCache::flushSitemap();
    }
}
