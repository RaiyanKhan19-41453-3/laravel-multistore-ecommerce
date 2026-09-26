<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The guest-fields rebuild briefly shipped without store_id; this
        // restores the column the tenant phase originally added. No-op on
        // fresh installs where the rebuild already carries it.
        if (! Schema::hasColumn('reviews', 'store_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            });

            try {
                $defaultId = DB::table('stores')->orderBy('id')->value('id');

                if ($defaultId) {
                    DB::table('reviews')->whereNull('store_id')->update(['store_id' => $defaultId]);
                }
            } catch (Throwable) {
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
