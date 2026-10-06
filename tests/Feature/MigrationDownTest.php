<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

function loadMigrationForRollback(string $file): Migration
{
    return require database_path('migrations/'.$file);
}

function foreignKeyColumns(string $table): array
{
    return collect(Schema::getForeignKeys($table))->pluck('columns')->flatten()->all();
}

it('recreates couriers with the historical composite unique so the older tenant down can run', function () {
    $courier = loadMigrationForRollback('2026_10_02_000000_make_couriers_code_config_driven.php');
    $tenantUniques = loadMigrationForRollback('2026_09_15_141516_make_tenant_uniques_per_store.php');

    $courier->down();

    expect(Schema::hasIndex('couriers', 'couriers_store_code_unique'))->toBeTrue()
        ->and(Schema::hasIndex('couriers', 'couriers_code_unique'))->toBeFalse()
        ->and(Schema::hasColumn('shipments', 'courier_id'))->toBeTrue()
        ->and(Schema::hasColumn('shipments', 'courier_code'))->toBeFalse();

    $tenantUniques->down();

    expect(Schema::hasIndex('couriers', 'couriers_store_code_unique'))->toBeFalse()
        ->and(Schema::hasIndex('couriers', 'couriers_code_unique'))->toBeTrue();

    $tenantUniques->up();
    $courier->up();

    expect(Schema::hasTable('couriers'))->toBeFalse()
        ->and(Schema::hasColumn('shipments', 'courier_id'))->toBeFalse()
        ->and(Schema::hasColumn('shipments', 'courier_code'))->toBeTrue();
});

it('keeps the performance index across the guest-reviews rebuild in both directions', function () {
    $migration = loadMigrationForRollback('2026_09_26_163207_add_guest_fields_to_reviews_table.php');

    $migration->down();

    expect(Schema::hasColumn('reviews', 'guest_email'))->toBeFalse()
        ->and(Schema::hasIndex('reviews', 'reviews_product_approved_idx'))->toBeTrue();

    $migration->up();

    expect(Schema::hasColumn('reviews', 'guest_email'))->toBeTrue()
        ->and(Schema::hasIndex('reviews', 'reviews_product_approved_idx'))->toBeTrue();
});

it('restores orders.user_id to not null when the guest-orders migration rolls back', function () {
    $migration = loadMigrationForRollback('2026_08_29_132826_make_user_id_nullable_on_orders_add_guest_fields.php');

    $migration->down();

    $userId = collect(Schema::getColumns('orders'))->firstWhere('name', 'user_id');

    expect($userId['nullable'])->toBeFalse()
        ->and(Schema::hasColumn('orders', 'guest_email'))->toBeFalse()
        ->and(foreignKeyColumns('orders'))->toContain('user_id');

    $migration->up();

    $userId = collect(Schema::getColumns('orders'))->firstWhere('name', 'user_id');

    expect($userId['nullable'])->toBeTrue()
        ->and(Schema::hasColumn('orders', 'guest_email'))->toBeTrue();
});

it('keeps orders and payments foreign keys while their helper indexes roll back', function () {
    $migration = loadMigrationForRollback('2026_08_28_172918_add_indexes_to_orders_and_payments_table.php');

    $migration->down();

    expect(Schema::hasIndex('orders', 'orders_user_id_index'))->toBeFalse()
        ->and(Schema::hasIndex('payments', 'payments_order_id_index'))->toBeFalse()
        ->and(foreignKeyColumns('orders'))->toContain('user_id')
        ->and(foreignKeyColumns('payments'))->toContain('order_id');

    $migration->up();

    expect(Schema::hasIndex('orders', 'orders_user_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('payments', 'payments_order_id_index'))->toBeTrue();
});
