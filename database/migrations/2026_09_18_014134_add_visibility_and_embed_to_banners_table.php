<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->boolean('show_title')->default(true)->after('text_layout');
            $table->boolean('show_subtitle')->default(true)->after('show_title');
            $table->boolean('show_button')->default(true)->after('show_subtitle');
            $table->string('media_type', 20)->default('image')->after('show_button');
            $table->string('iframe_url', 2000)->nullable()->after('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['show_title', 'show_subtitle', 'show_button', 'media_type', 'iframe_url']);
        });
    }
};
