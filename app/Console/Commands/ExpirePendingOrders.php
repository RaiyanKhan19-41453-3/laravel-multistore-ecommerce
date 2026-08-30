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
        $expiredOrders = Order::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($expiredOrders as $order) {
            try {
                $orderService->expireOrder($order);
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to expire order #{$order->order_number}: {$e->getMessage()}");
            }
        }

        $this->info("Expired {$count} pending order(s).");

        return Command::SUCCESS;
    }
}
