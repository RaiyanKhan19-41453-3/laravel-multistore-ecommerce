<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Support\Facades\Schema;

/**
 * Holds the store resolved for the current request.
 *
 * Always safe to call: returns null when the stores table does not exist
 * yet (fresh install) or no store could be resolved. Callers must fall
 * back to global settings / legacy behavior when null.
 */
class CurrentStore
{
    private ?Store $store = null;

    public function set(?Store $store): void
    {
        $this->store = $store;
    }

    public function get(): ?Store
    {
        return $this->store;
    }

    public function id(): ?int
    {
        return $this->store?->id;
    }

    public function check(): bool
    {
        return $this->store !== null;
    }

    public function forget(): void
    {
        $this->store = null;
    }

    /**
     * Resolve the fallback store without throwing on fresh installs.
     */
    public function default(): ?Store
    {
        if ($this->store) {
            return $this->store;
        }

        try {
            if (! Schema::hasTable('stores')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return Store::default();
    }

    /**
     * Store id to scope tenant queries and scoped unique validation.
     * Null when no store exists yet (fresh install / early tests):
     * callers must leave queries unscoped in that case so legacy
     * single-store behavior keeps working.
     */
    public function scopeId(): ?int
    {
        try {
            return $this->id() ?? $this->default()?->id;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Return the explicitly set store ID without falling back to the
     * default store. Used by the BelongsToStore global scope to avoid
     * auto-scoping in contexts where no store was explicitly resolved
     * (tests, super-admin, console commands).
     */
    public function explicitlySetId(): ?int
    {
        return $this->store?->id;
    }

    /**
     * Constrain a tenant query to the current store. No-op when no store
     * is resolved so console, jobs, and store-less tests keep working.
     *
     * @template TBuilder of \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function applyScope($query, ?string $table = null)
    {
        $id = $this->scopeId();

        if ($id === null) {
            return $query;
        }

        $table ??= $query->getModel()->getTable();

        return $query->where("{$table}.store_id", $id);
    }
}
