<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->string('subtitle', 500)->nullable();
            $table->string('subtitle_ar', 500)->nullable();
            $table->string('button_label', 50)->nullable();
            $table->string('button_label_ar', 50)->nullable();
            $table->string('button_link', 500)->nullable();
            // single = 1 full-width image, double = 2 columns, quad = 4 columns.
            $table->string('layout', 20)->default('single');
            // split = text left + button right, left = text only, center = centered text + button after images.
            $table->string('text_layout', 20)->default('split');
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['store_id', 'is_active', 'sort_order'], 'banners_store_act_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
