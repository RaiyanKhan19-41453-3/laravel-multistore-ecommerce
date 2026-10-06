<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retry attempts are keyed per order so a double-clicked "pay again"
     * replays the stored response instead of opening a second gateway
     * session. redirect_url is persisted for the same reason: the replay
     * path must not call the gateway again.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('gateway_transaction_id');
            $table->text('redirect_url')->nullable()->after('idempotency_key');
            $table->unique(['order_id', 'idempotency_key'], 'payments_order_idempotency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_order_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'redirect_url']);
        });
    }
};
