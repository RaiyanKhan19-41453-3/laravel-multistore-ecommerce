<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('price', 'products_price_idx');
            $table->index('is_active', 'products_active_idx');
            $table->index('is_featured', 'products_featured_idx');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['product_id', 'is_approved'], 'reviews_product_approved_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_product_approved_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_featured_idx');
            $table->dropIndex('products_active_idx');
            $table->dropIndex('products_price_idx');
        });
    }
};
