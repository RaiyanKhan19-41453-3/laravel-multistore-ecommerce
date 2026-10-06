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
            DB::statement('ALTER TABLE `orders` MODIFY `user_id` BIGINT UNSIGNED NULL');
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('guest_email')->nullable()->after('user_id');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->index('guest_email');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['guest_email']);
            $table->dropColumn(['guest_email', 'guest_phone']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE `orders` MODIFY `user_id` BIGINT UNSIGNED NOT NULL');
        } else {
            // ->constrained() returns the foreign-key definition, so
            // chaining ->change() onto it would flag the FK instead of the
            // column; SQLite then compiles the column as a plain ADD and
            // dies on "duplicate column name". The FK already exists and
            // the table rebuild preserves it — only nullability flips.
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->change();
            });
        }
    }
};
