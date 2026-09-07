<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * User carts created before expiry tracking existed have NULL expires_at
     * and hold reservations forever (the cleanup cron only sees dated rows).
     * Give them an expiry derived from last activity.
     */
    public function up(): void
    {
        $expression = in_array(DB::getDriverName(), ['mysql', 'mariadb'])
            ? 'DATE_ADD(updated_at, INTERVAL 30 DAY)'
            : "DATETIME(updated_at, '+30 days')";

        DB::table('carts')
            ->where('status', 'active')
            ->whereNull('expires_at')
            ->update([
                'expires_at' => DB::raw($expression),
            ]);
    }

    public function down(): void
    {
        // Data backfill is intentionally irreversible.
    }
};
