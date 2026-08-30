<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->boolean('is_fallback')->default(false)->after('cities');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->dropColumn('is_fallback');
        });
    }
};
