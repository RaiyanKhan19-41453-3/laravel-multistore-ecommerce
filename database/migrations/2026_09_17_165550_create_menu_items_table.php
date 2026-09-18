<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->string('type', 20)->default('url');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('click_behavior', 20)->default('navigate');
            $table->string('display', 20)->default('auto');
            $table->string('promo_image', 500)->nullable();
            $table->string('promo_title')->nullable();
            $table->string('promo_link', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['store_id', 'parent_id', 'sort_order'], 'menu_items_store_parent_sort_idx');
            $table->index(['store_id', 'is_active'], 'menu_items_store_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
