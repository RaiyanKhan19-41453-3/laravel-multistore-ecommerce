<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('short_description_ar')->nullable()->after('short_description');
            $table->text('description_ar')->nullable()->after('description');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->text('description_ar')->nullable()->after('description');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->text('description_ar')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'description_ar']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'description_ar']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'short_description_ar', 'description_ar']);
        });
    }
};
