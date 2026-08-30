<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Services\CartService;
use Illuminate\Console\Command;

class CleanupStaleCarts extends Command
{
    protected $signature = 'carts:cleanup';

    protected $description = 'Mark expired guest carts as expired and release their reserved inventory';

    public function handle(CartService $cartService): int
    {
        $staleCarts = Cart::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($staleCarts as $cart) {
            try {
                $cartService->clearCart($cart);
                $cart->update(['status' => 'expired']);
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to expire cart {$cart->id}: {$e->getMessage()}");
            }
        }

        $this->info("Expired {$count} stale carts.");

        return self::SUCCESS;
    }
}
