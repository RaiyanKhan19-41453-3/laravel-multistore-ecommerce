<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('eyebrow', 100)->nullable();
            $table->string('eyebrow_ar', 100)->nullable();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->string('subtitle', 500)->nullable();
            $table->string('subtitle_ar', 500)->nullable();
            $table->string('cta_label', 50)->nullable();
            $table->string('cta_label_ar', 50)->nullable();
            $table->string('cta_link', 500)->nullable();
            $table->string('image', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['store_id', 'is_active', 'sort_order'], 'hero_slides_store_act_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
