<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_token', 36)->nullable()->unique();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        DB::table('carts_new')->insertUsing(
            ['id', 'user_id', 'guest_token', 'coupon_id', 'status', 'expires_at', 'created_at', 'updated_at'],
            DB::table('carts'),
        );

        Schema::dropIfExists('carts');
        Schema::rename('carts_new', 'carts');
    }

    public function down(): void
    {
        Schema::create('carts_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('guest_token', 36)->nullable()->unique();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        DB::table('carts_new')->insertUsing(
            ['id', 'user_id', 'guest_token', 'coupon_id', 'status', 'expires_at', 'created_at', 'updated_at'],
            DB::table('carts'),
        );

        Schema::dropIfExists('carts');
        Schema::rename('carts_new', 'carts');
    }
};
