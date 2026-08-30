<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('free_shipping_min', 10, 2)->nullable()->comment('Minimum order amount for free shipping');
            $table->timestamps();

            $table->unique(['shipping_method_id', 'shipping_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
