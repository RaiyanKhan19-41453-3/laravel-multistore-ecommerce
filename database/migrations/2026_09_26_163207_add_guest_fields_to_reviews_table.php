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
        // Drop first: SQLite keeps index names global across tables, so
        // the rebuild below would collide with these.
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_user_product_unique');
            $table->dropIndex(['product_id']);
            $table->dropIndex(['is_approved']);
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

            $table->unique(['user_id', 'product_id'], 'reviews_user_product_unique');
            $table->index('product_id');
            $table->index('is_approved');
            $table->index(['product_id', 'guest_email'], 'reviews_product_guest_email_index');
        });

        $columns = ['id', 'user_id', 'product_id', 'store_id', 'rating', 'title', 'body', 'is_approved', 'verified_purchase', 'created_at', 'updated_at'];
        DB::table('reviews_new')->insertUsing($columns, DB::table('reviews')->select($columns));

        Schema::drop('reviews');
        Schema::rename('reviews_new', 'reviews');

        // Auto-created indexes carry the temp table name on every driver:
        // normalize them so the final schema matches a never-rebuilt table.
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_new_product_id_index');
            $table->dropIndex('reviews_new_is_approved_index');
            $table->index('product_id');
            $table->index('is_approved');
        });

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

        // Drop first, like up(): SQLite keeps index names global across
        // tables, so the rebuild below would collide with these.
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_user_product_unique');
            $table->dropIndex(['product_id']);
            $table->dropIndex(['is_approved']);
            $table->dropIndex('reviews_product_guest_email_index');
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
            $table->index('product_id');
            $table->index('is_approved');
        });

        $columns = ['id', 'user_id', 'product_id', 'store_id', 'rating', 'title', 'body', 'is_approved', 'verified_purchase', 'created_at', 'updated_at'];
        DB::table('reviews_old')->insertUsing($columns, DB::table('reviews')->select($columns));

        Schema::drop('reviews');
        Schema::rename('reviews_old', 'reviews');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_old_product_id_index');
            $table->dropIndex('reviews_old_is_approved_index');
            $table->index('product_id');
            $table->index('is_approved');
        });

        Schema::enableForeignKeyConstraints();
    }
};
