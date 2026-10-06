<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Services\CatalogCache;

class InventoryObserver
{
    /**
     * Card badges come from the cached featured payload: refresh it when
     * a mutation flips availability across zero. increment()/decrement()
     * fire updated (verified), so reserve/release/deduct/restore are all
     * covered without touching the hot paths.
     */
    public function updated(Inventory $inventory): void
    {
        $wasAvailable = (($inventory->getOriginal('quantity') ?? 0)
            - ($inventory->getOriginal('reserved_quantity') ?? 0)) > 0;

        if ($wasAvailable !== ($inventory->getAvailableQuantity() > 0)) {
            CatalogCache::flushFeatured();
        }
    }

    public function created(Inventory $inventory): void
    {
        if ($inventory->getAvailableQuantity() > 0) {
            CatalogCache::flushFeatured();
        }
    }

    public function deleted(Inventory $inventory): void
    {
        CatalogCache::flushFeatured();
    }
}
