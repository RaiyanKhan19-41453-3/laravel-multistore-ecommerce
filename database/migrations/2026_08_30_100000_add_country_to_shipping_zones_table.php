<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->string('country')->default('Bangladesh')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
