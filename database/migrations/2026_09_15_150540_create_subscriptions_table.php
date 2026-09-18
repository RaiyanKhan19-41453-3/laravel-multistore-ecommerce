<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique('subscriptions_store_unique')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_started_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->string('gateway', 32)->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
