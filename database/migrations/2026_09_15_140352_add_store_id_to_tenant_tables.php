<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant tables get a nullable store_id first so existing single-store
     * installs keep working. Null means "default store" until backfilled.
     * New rows auto-fill centrally in AppServiceProvider; enforcement
     * (per-store uniques, query scoping) comes in a later phase.
     *
     * @return array<int, string>
     */
    public static function tenantTables(): array
    {
        return [
            'products',
            'product_variants',
            'attributes',
            'attribute_values',
            'categories',
            'brands',
            'product_images',
            'inventories',
            'inventory_movements',
            'carts',
            'cart_items',
            'orders',
            'order_items',
            'addresses',
            'payments',
            'shipments',
            'discounts',
            'coupons',
            'coupon_redemptions',
            'shipping_methods',
            'shipping_zones',
            'shipping_rates',
            'couriers',
            'cms_pages',
            'reviews',
            'wishlists',
            'zatca_devices',
            'zatca_documents',
        ];
    }

    public function up(): void
    {
        foreach (self::tenantTables() as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        // Existing installs: ensure a default store exists, then adopt orphans.
        try {
            if (Schema::hasTable('stores')) {
                $defaultId = DB::table('stores')->orderBy('id')->value('id');

                if (! $defaultId) {
                    $defaultId = DB::table('stores')->insertGetId([
                        'name' => config('store.name', 'My Store'),
                        'slug' => 'default',
                        'country' => config('store.country', 'BD'),
                        'currency' => config('store.currency', 'BDT'),
                        'locale' => config('store.locale', 'en'),
                        'timezone' => config('store.timezone', 'Asia/Dhaka'),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach (self::tenantTables() as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'store_id')) {
                        DB::table($table)->whereNull('store_id')->update(['store_id' => $defaultId]);
                    }
                }
            }
        } catch (Throwable) {
            // Fresh installs and tests seed the default store via seeder instead.
        }
    }

    public function down(): void
    {
        foreach (self::tenantTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['store_id']);
                $table->dropColumn('store_id');
            });
        }
    }
};
