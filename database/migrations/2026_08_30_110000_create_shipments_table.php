<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('courier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('courier_order_id')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('shipping_cost', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_type')->default('manual')->after('shipping_estimated_days');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_type');
        });

        Schema::dropIfExists('shipments');
    }
};
