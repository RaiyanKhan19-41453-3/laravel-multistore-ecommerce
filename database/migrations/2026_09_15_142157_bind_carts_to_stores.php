<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One active cart per owner per store. The old global guest_token
     * unique becomes (store_id, guest_token) so the same guest (same
     * cookie) can hold a cart in every store they visit.
     */
    public function up(): void
    {
        if (! Schema::hasTable('carts') || ! Schema::hasColumn('carts', 'store_id')) {
            return;
        }

        // The legacy unique is named carts_new_guest_token_unique after an
        // old table-rebuild migration; fresh builds may use the
        // conventional name instead. Drop whichever one exists.
        $existing = self::existingIndexNames();

        foreach (['carts_new_guest_token_unique', 'carts_guest_token_unique'] as $candidate) {
            if (in_array($candidate, $existing, true)) {
                Schema::table('carts', function (Blueprint $table) use ($candidate) {
                    $table->dropUnique($candidate);
                });
            }
        }

        Schema::table('carts', function (Blueprint $table) {
            $table->unique(['store_id', 'guest_token'], 'carts_store_guest_unique');
            $table->index(['user_id', 'status', 'store_id'], 'carts_user_status_store_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('carts') || ! Schema::hasColumn('carts', 'store_id')) {
            return;
        }

        // FKs first: MySQL backs them with our indexes' leftmost prefixes
        // (store_id FK via the composite, user_id FK via the plain index),
        // so dropping any index first fails with errno 1553. Names are
        // resolved, not assumed: an old rebuild left legacy carts_new_*
        // constraint names behind.
        foreach (['store_id', 'user_id', 'coupon_id'] as $column) {
            if ($fk = self::foreignKeyName('carts', $column)) {
                Schema::table('carts', function (Blueprint $table) use ($fk) {
                    $table->dropForeign($fk);
                });
            }
        }

        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique('carts_store_guest_unique');
            $table->dropIndex('carts_user_status_store_index');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->unique('guest_token');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
        });
    }

    /**
     * @return array<int, string>
     */
    private static function existingIndexNames(): array
    {
        try {
            return Schema::getIndexListing('carts');
        } catch (Throwable) {
            return ['carts_new_guest_token_unique', 'carts_guest_token_unique'];
        }
    }

    private static function foreignKeyName(string $table, string $column): ?string
    {
        try {
            foreach (Schema::getForeignKeys($table) as $fk) {
                $columns = array_map(
                    fn ($c) => is_array($c) ? ($c['column_name'] ?? null) : (string) $c,
                    $fk['columns'] ?? []
                );

                if ($columns === [$column]) {
                    return $fk['name'];
                }
            }
        } catch (Throwable) {
            // Fall through to conventional name below.
        }

        return "{$table}_{$column}_foreign";
    }
};
