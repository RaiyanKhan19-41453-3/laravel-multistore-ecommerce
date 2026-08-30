<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });
    }
};
