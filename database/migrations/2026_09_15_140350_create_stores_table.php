<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique('stores_slug_unique');
            $table->string('domain')->nullable()->unique('stores_domain_unique');
            $table->string('country', 2)->default('BD');
            $table->string('currency', 3)->default('BDT');
            $table->string('locale', 5)->default('en');
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
