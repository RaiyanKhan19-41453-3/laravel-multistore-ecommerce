<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('sort_order');
            $table->string('meta_description')->nullable()->after('meta_title');
            $table->string('og_image')->nullable()->after('meta_description');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('sort_order');
            $table->string('meta_description')->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'og_image']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
