<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings become per-store overrides with NULL store_id kept as the
     * platform global default. Existing rows are adopted by the default
     * store so single-store installs read identical values afterwards.
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        if (! Schema::hasColumn('settings', 'store_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique('settings_key_unique');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unique(['store_id', 'key'], 'settings_store_key_unique');
        });

        try {
            if (Schema::hasTable('stores')) {
                $defaultId = DB::table('stores')->orderBy('id')->value('id');

                if ($defaultId) {
                    DB::table('settings')->whereNull('store_id')->update(['store_id' => $defaultId]);
                }
            }
        } catch (Throwable) {
            // Fresh installs seed through SettingsSeeder instead.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        // Rolling back with per-store overrides present would violate the
        // global unique, so keep only the default store's rows.
        try {
            if (Schema::hasTable('stores') && Schema::hasColumn('settings', 'store_id')) {
                $defaultId = DB::table('stores')->orderBy('id')->value('id');

                DB::table('settings')->whereNotNull('store_id')
                    ->where('store_id', '!=', $defaultId)
                    ->delete();
                DB::table('settings')->where('store_id', $defaultId)->update(['store_id' => null]);
            }
        } catch (Throwable) {
            // Best effort; the schema reversal below still runs.
        }

        Schema::table('settings', function (Blueprint $table) {
            // FK first: MySQL backs it with the composite's leftmost
            // prefix, so dropping the unique first fails with errno 1553.
            $table->dropForeign(['store_id']);
            $table->dropUnique('settings_store_key_unique');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unique('key', 'settings_key_unique');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('store_id');
        });
    }
};
