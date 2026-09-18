<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('owner');
            $table->timestamps();

            $table->unique(['store_id', 'user_id'], 'store_user_store_user_unique');
            $table->index('user_id', 'store_user_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_user');
    }
};
