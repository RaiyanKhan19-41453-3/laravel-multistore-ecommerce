<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_reset_otps', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('reset_token');
            $table->timestamp('reset_expires_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_otps', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'reset_expires_at']);
        });
    }
};
