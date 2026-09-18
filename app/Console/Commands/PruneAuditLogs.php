<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days=365 : Delete audit logs older than this many days}';

    protected $description = 'Delete audit log entries older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = AuditLog::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Pruned {$deleted} audit log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
