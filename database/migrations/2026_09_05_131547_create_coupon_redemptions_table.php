<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('per_user_limit')->nullable()->default(1)->after('usage_count');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('identifier', 255)->comment('user:{id} for customers, guest:{email} for guests');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['coupon_id', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('per_user_limit');
        });
    }
};
