<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The merchant whose data the current admin request manages.
 *
 * Null selection means the platform-wide view (all stores) — today's
 * behavior, kept for super-admins and staff without store memberships.
 * An explicit selection scopes admin listings and directs creations,
 * validation, and settings reads/writes at that store.
 */
class AdminStoreContext
{
    public const SESSION_KEY = 'admin_store_id';

    private ?Store $override = null;

    private bool $hasOverride = false;

    /**
     * Request-level selection (e.g. explicit API header) taking precedence
     * over the session. Set by ResolveAdminStore; keeps header-driven and
     * session-driven requests consistent for every consumer.
     */
    public function setOverride(?Store $store): void
    {
        $this->override = $store;
        $this->hasOverride = true;
    }

    /**
     * Reset the request-level selection. ResolveAdminStore calls this on
     * every request so a header from one request (or a stale singleton in
     * tests and Octane workers) never leaks into the next request.
     */
    public function clearOverride(): void
    {
        $this->override = null;
        $this->hasOverride = false;
    }

    public function selectedId(?User $user = null): ?int
    {
        try {
            if (! Schema::hasTable('stores')) {
                return null;
            }

            if ($this->hasOverride) {
                return $this->override?->id;
            }

            $user ??= auth()->user();

            $id = session(self::SESSION_KEY);

            if ($id === null) {
                // Staff with memberships are locked to their stores: they
                // never see the platform-wide view. Legacy staff without
                // memberships (and super-admins) keep it.
                return $this->defaultMembershipId($user);
            }

            $store = Store::find($id);

            if (! $store || ($user && ! $this->canAccess($user, $store))) {
                session()->forget(self::SESSION_KEY);

                return $this->defaultMembershipId($user);
            }

            return $store->id;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Return the explicitly selected store ID (header override or session)
     * without falling back to the user's default membership. Used by the
     * BelongsToStore global scope to avoid auto-scoping in contexts where
     * no explicit store was chosen (tests, super-admin, direct queries).
     */
    public function explicitlySelectedId(): ?int
    {
        try {
            if ($this->hasOverride) {
                return $this->override?->id;
            }

            $id = session(self::SESSION_KEY);

            return $id !== null ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function selected(?User $user = null): ?Store
    {
        $id = $this->selectedId($user);

        return $id ? Store::find($id) : null;
    }

    public function isExplicit(?User $user = null): bool
    {
        return $this->selectedId($user) !== null;
    }

    /**
     * Stores the user may manage: every store for super-admins,
     * membership stores for everyone else.
     *
     * @return Collection<int, Store>
     */
    public function availableStores(User $user): Collection
    {
        try {
            if (! Schema::hasTable('stores')) {
                return new Collection;
            }

            if ($user->hasRole('super-admin')) {
                return Store::active()->orderBy('name')->get();
            }

            if (! Schema::hasTable('store_user')) {
                return new Collection;
            }

            return $user->belongsToMany(Store::class, 'store_user')
                ->where('stores.is_active', true)
                ->orderBy('stores.name')
                ->get();
        } catch (\Throwable) {
            return new Collection;
        }
    }

    public function canAccess(User $user, Store $store): bool
    {
        try {
            if (! $store->is_active) {
                return false;
            }

            if ($user->hasRole('super-admin')) {
                return true;
            }

            if (! Schema::hasTable('store_user')) {
                return false;
            }

            return $user->belongsToMany(Store::class, 'store_user')
                ->where('stores.id', $store->id)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function isOwner(User $user, Store $store): bool
    {
        try {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            if (! Schema::hasTable('store_user')) {
                return false;
            }

            return $user->belongsToMany(Store::class, 'store_user')
                ->where('stores.id', $store->id)
                ->wherePivot('role', 'owner')
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * First manageable store for locked-in staff, or null when the user
     * keeps the platform-wide view. Memoized per user id: singletons
     * survive across requests on Octane, users do not.
     *
     * @var array<int, ?int>
     */
    private array $membershipDefaults = [];

    private function defaultMembershipId(?User $user): ?int
    {
        try {
            if (! $user || ! Schema::hasTable('store_user')) {
                return null;
            }

            if (array_key_exists($user->id, $this->membershipDefaults)) {
                return $this->membershipDefaults[$user->id];
            }

            if ($user->hasRole('super-admin')) {
                return $this->membershipDefaults[$user->id] = null;
            }

            return $this->membershipDefaults[$user->id] = $user->belongsToMany(Store::class, 'store_user')
                ->where('stores.is_active', true)
                ->orderBy('stores.id')
                ->first()?->id;
        } catch (\Throwable) {
            return null;
        }
    }

    public function select(User $user, ?int $storeId): bool
    {
        if ($storeId === null) {
            try {
                if ($user->hasRole('super-admin')) {
                    session()->forget(self::SESSION_KEY);

                    return true;
                }
            } catch (\Throwable) {
                return false;
            }

            return false;
        }

        try {
            $store = Store::find($storeId);
        } catch (\Throwable) {
            return false;
        }

        if (! $store || ! $this->canAccess($user, $store)) {
            return false;
        }

        session([self::SESSION_KEY => $store->id]);

        return true;
    }

    /**
     * Constrain an admin listing to the selected store. No-op without an
     * explicit selection, so the platform-wide view keeps working.
     *
     * @template TBuilder of \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function scope($query, ?string $table = null, ?User $user = null)
    {
        $id = $this->selectedId($user);

        if ($id === null) {
            return $query;
        }

        $table ??= $query->getModel()->getTable();

        return $query->where("{$table}.store_id", $id);
    }

    /**
     * Store anchoring cross-record links (category/brand/attribute ids in
     * request bodies): the parent record's store when known, else the
     * current store. Keeps discounts, products, and variants from linking
     * to another store's taxonomy.
     */
    public function anchorStoreId(?Model $parent = null): ?int
    {
        try {
            return $parent?->store_id ?? app(CurrentStore::class)->scopeId();
        } catch (\Throwable) {
            return $parent?->store_id;
        }
    }

    /**
     * Exists rule limited to one store's rows. Null store leaves the rule
     * unscoped (legacy rows reference legacy rows).
     */
    public function existsInStore(string $table, ?int $storeId, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column);

        if ($storeId !== null) {
            $rule->where('store_id', $storeId);
        }

        return $rule;
    }
}
