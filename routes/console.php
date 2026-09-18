<?php

use App\Console\Commands\CleanupStaleCarts;
use App\Console\Commands\ExpirePendingOrders;
use App\Console\Commands\PruneAuditLogs;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ExpirePendingOrders::class)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command(CleanupStaleCarts::class)
    ->daily();

Schedule::command(PruneAuditLogs::class)
    ->monthly();
