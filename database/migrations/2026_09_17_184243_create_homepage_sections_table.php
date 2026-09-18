<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 40);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'key'], 'homepage_sections_store_key_unique');
            $table->index(['store_id', 'is_active', 'sort_order'], 'homepage_sections_store_act_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
    }
};
