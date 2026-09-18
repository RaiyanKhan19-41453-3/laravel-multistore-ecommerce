<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Expire pending orders that have exceeded their reservation TTL';

    public function handle(OrderService $orderService): int
    {
        $count = 0;

        Order::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(200, function ($orders) use ($orderService, &$count) {
                foreach ($orders as $order) {
                    try {
                        $orderService->expireOrder($order);
                        $count++;
                    } catch (\Exception $e) {
                        $this->error("Failed to expire order #{$order->order_number}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Expired {$count} pending order(s).");

        return Command::SUCCESS;
    }
}
