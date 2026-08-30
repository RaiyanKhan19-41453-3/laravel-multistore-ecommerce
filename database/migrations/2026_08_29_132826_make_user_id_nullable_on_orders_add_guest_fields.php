<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('guest_email')->nullable()->after('user_id');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->index('guest_email');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->change();
            $table->dropIndex(['guest_email']);
            $table->dropColumn(['guest_email', 'guest_phone']);
        });
    }
};
