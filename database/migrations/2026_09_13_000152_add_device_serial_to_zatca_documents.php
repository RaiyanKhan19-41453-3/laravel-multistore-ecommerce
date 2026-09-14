<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zatca_documents', function (Blueprint $table) {
            $table->string('device_serial')->default('default')->after('order_id');
            $table->index(['device_serial', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('zatca_documents', function (Blueprint $table) {
            $table->dropIndex(['device_serial', 'id']);
            $table->dropColumn('device_serial');
        });
    }
};
