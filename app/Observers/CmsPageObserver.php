<?php

namespace App\Observers;

use App\Models\CmsPage;
use App\Services\CatalogCache;

class CmsPageObserver
{
    public function saved(CmsPage $page): void
    {
        CatalogCache::flushPages();
        CatalogCache::flushSitemap();
    }

    public function deleted(CmsPage $page): void
    {
        CatalogCache::flushPages();
        CatalogCache::flushSitemap();
    }

    public function restored(CmsPage $page): void
    {
        CatalogCache::flushPages();
    }
}
