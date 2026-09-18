<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\CatalogCache;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        CatalogCache::flushCategories();
        CatalogCache::flushSitemap();
    }

    public function deleted(Category $category): void
    {
        CatalogCache::flushCategories();
        CatalogCache::flushSitemap();
    }

    public function restored(Category $category): void
    {
        CatalogCache::flushCategories();
        CatalogCache::flushSitemap();
    }
}
