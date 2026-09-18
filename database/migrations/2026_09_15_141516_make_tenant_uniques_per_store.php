<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global uniques become per-store composites so two stores can use
     * the same slug, SKU, coupon code, or order number sequence.
     * store_id stays nullable (legacy/tests); backfilled rows all carry
     * the default store id from the earlier migration.
     *
     * @return array<string, array{unique: array<int, string>, composite: array<int, string>, name: string}>
     */
    public static function conversions(): array
    {
        return [
            'products.slug' => [
                'unique' => ['slug'],
                'composite' => ['store_id', 'slug'],
                'name' => 'products_store_slug_unique',
            ],
            'products.sku' => [
                'unique' => ['sku'],
                'composite' => ['store_id', 'sku'],
                'name' => 'products_store_sku_unique',
            ],
            'product_variants.sku' => [
                'unique' => ['sku'],
                'composite' => ['store_id', 'sku'],
                'name' => 'variants_store_sku_unique',
            ],
            'categories.slug' => [
                'unique' => ['slug'],
                'composite' => ['store_id', 'slug'],
                'name' => 'categories_store_slug_unique',
            ],
            'brands.slug' => [
                'unique' => ['slug'],
                'composite' => ['store_id', 'slug'],
                'name' => 'brands_store_slug_unique',
            ],
            'attributes.slug' => [
                'unique' => ['slug'],
                'composite' => ['store_id', 'slug'],
                'name' => 'attributes_store_slug_unique',
            ],
            'cms_pages.slug' => [
                'unique' => ['slug'],
                'composite' => ['store_id', 'slug'],
                'name' => 'pages_store_slug_unique',
            ],
            'coupons.code' => [
                'unique' => ['code'],
                'composite' => ['store_id', 'code'],
                'name' => 'coupons_store_code_unique',
            ],
            'couriers.code' => [
                'unique' => ['code'],
                'composite' => ['store_id', 'code'],
                'name' => 'couriers_store_code_unique',
            ],
            'orders.order_number' => [
                'unique' => ['order_number'],
                'composite' => ['store_id', 'order_number'],
                'name' => 'orders_store_number_unique',
            ],
        ];
    }

    public function up(): void
    {
        foreach (self::conversions() as $key => $conversion) {
            [$table] = explode('.', $key, 2);

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) use ($conversion) {
                $table->dropUnique($conversion['unique']);
            });

            Schema::table($table, function (Blueprint $table) use ($conversion) {
                $table->unique($conversion['composite'], $conversion['name']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::conversions() as $key => $conversion) {
            [$table] = explode('.', $key, 2);

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            // FK first: MySQL may back it with the composite's leftmost
            // prefix, so dropping the unique first fails with errno 1553.
            // The FK is recreated afterwards (the store_id column itself
            // belongs to the earlier add_store_id migration and stays).
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['store_id']);
            });

            Schema::table($table, function (Blueprint $table) use ($conversion) {
                $table->dropUnique($conversion['name']);
            });

            Schema::table($table, function (Blueprint $table) use ($conversion) {
                $table->unique($conversion['unique']);
            });

            Schema::table($table, function (Blueprint $table) {
                $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            });
        }
    }
};
