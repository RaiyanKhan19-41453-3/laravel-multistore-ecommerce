<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->boolean('show_eyebrow')->default(true)->after('cta_link');
            $table->boolean('show_title')->default(true)->after('show_eyebrow');
            $table->boolean('show_subtitle')->default(true)->after('show_title');
            $table->boolean('show_button')->default(true)->after('show_subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn(['show_eyebrow', 'show_title', 'show_subtitle', 'show_button']);
        });
    }
};
