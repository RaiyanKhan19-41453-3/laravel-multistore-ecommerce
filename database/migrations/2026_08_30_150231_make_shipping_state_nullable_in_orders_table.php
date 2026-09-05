<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `change()` requires doctrine/dbal on MySQL, so use raw SQL there.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE `orders` MODIFY `shipping_state` VARCHAR(255) NULL');
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_state')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE `orders` MODIFY `shipping_state` VARCHAR(255) NOT NULL');
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_state')->nullable(false)->change();
            });
        }
    }
};
