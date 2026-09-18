<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table) {
            $table->dropUnique('homepage_sections_store_key_unique');
            $table->foreignId('banner_id')->nullable()->after('key')->constrained('banners')->cascadeOnDelete();
            $table->index(['store_id', 'sort_order'], 'homepage_sections_store_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table) {
            $table->dropIndex('homepage_sections_store_sort_idx');
            $table->dropForeign(['banner_id']);
            $table->dropColumn('banner_id');
            $table->unique(['store_id', 'key'], 'homepage_sections_store_key_unique');
        });
    }
};
