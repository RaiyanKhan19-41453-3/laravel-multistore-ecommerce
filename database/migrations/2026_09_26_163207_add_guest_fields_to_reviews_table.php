<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One code path for MySQL and SQLite (no doctrine/dbal, no MODIFY):
     * rebuild the table. Reviews is not referenced by foreign keys
     * anywhere, so a copy is safe.
     */
    public function up(): void
    {
        // InnoDB refuses to drop an index a foreign key is using
        // (error 1553): user_id's FK backs onto the composite unique and
        // product_id's FK onto its index, so the constraints go first.
        // SQLite ignores FK names but tolerates the same sequence.
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['product_id']);
            $table->dropUnique('reviews_user_product_unique');
            $table->dropIndex(['product_id']);
            $table->dropIndex(['is_approved']);
            // SQLite index names are global across tables, so the old
            // composite has to go before reviews_new can claim the name.
            $table->dropIndex('reviews_product_approved_idx');
        });

        Schema::disableForeignKeyConstraints();

        Schema::create('reviews_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('rating')->unsigned();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->boolean('verified_purchase')->default(false);
            $table->string('guest_name', 100)->nullable();
            $table->string('guest_email', 255)->nullable();
            $table->timestamps();

            // Explicit names so the indexes survive the rename with their
            // final names on every driver (SQLite index names are global
            // across tables, which is why they are dropped above first).
            $table->unique(['user_id', 'product_id'], 'reviews_user_product_unique');
            $table->index('product_id', 'reviews_product_id_index');
            $table->index('is_approved', 'reviews_is_approved_index');
            $table->index(['product_id', 'is_approved'], 'reviews_product_approved_idx');
            $table->index(['product_id', 'guest_email'], 'reviews_product_guest_email_index');
        });

        $columns = ['id', 'user_id', 'product_id', 'store_id', 'rating', 'title', 'body', 'is_approved', 'verified_purchase', 'created_at', 'updated_at'];
        DB::table('reviews_new')->insertUsing($columns, DB::table('reviews')->select($columns));

        Schema::drop('reviews');
        Schema::rename('reviews_new', 'reviews');

        $this->normalizeForeignKeyNames('reviews_new');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * Guest rows cannot survive a non-nullable user_id: dropping them
     * is the documented cost of rolling back.
     */
    public function down(): void
    {
        // Guest rows cannot survive a non-nullable user_id: dropping them
        // is the documented cost of rolling back.
        DB::table('reviews')->whereNull('user_id')->delete();

        // Same FK-before-index ordering as up(): InnoDB blocks index
        // drops that a foreign key still depends on.
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['product_id']);
            $table->dropUnique('reviews_user_product_unique');
            $table->dropIndex(['product_id']);
            $table->dropIndex(['is_approved']);
            $table->dropIndex('reviews_product_guest_email_index');
            $table->dropIndex('reviews_product_approved_idx');
        });

        Schema::disableForeignKeyConstraints();

        Schema::create('reviews_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('rating')->unsigned();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->boolean('verified_purchase')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'product_id'], 'reviews_user_product_unique');
            $table->index('product_id', 'reviews_product_id_index');
            $table->index('is_approved', 'reviews_is_approved_index');
            // The performance index from 2026_09_15_000001 predates this
            // rebuild; its down() drops it later in a full rollback, so the
            // recreated table has to carry it.
            $table->index(['product_id', 'is_approved'], 'reviews_product_approved_idx');
        });

        $columns = ['id', 'user_id', 'product_id', 'store_id', 'rating', 'title', 'body', 'is_approved', 'verified_purchase', 'created_at', 'updated_at'];
        DB::table('reviews_old')->insertUsing($columns, DB::table('reviews')->select($columns));

        Schema::drop('reviews');
        Schema::rename('reviews_old', 'reviews');

        $this->normalizeForeignKeyNames('reviews_old');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Constraint names are baked in at creation time and RENAME TABLE
     * does not update them, so the rebuilt table's foreign keys are
     * still called {temp}_*_foreign. SQLite stores no FK names at all;
     * only MySQL needs (and supports) this cleanup.
     */
    private function normalizeForeignKeyNames(string $tempTable): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) use ($tempTable) {
            $table->dropForeign("{$tempTable}_user_id_foreign");
            $table->dropForeign("{$tempTable}_product_id_foreign");
            $table->dropForeign("{$tempTable}_store_id_foreign");

            $table->foreign('user_id', 'reviews_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('product_id', 'reviews_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('store_id', 'reviews_store_id_foreign')->references('id')->on('stores')->nullOnDelete();
        });
    }
};
