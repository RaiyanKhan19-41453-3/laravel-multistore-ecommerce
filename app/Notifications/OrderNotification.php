<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

abstract class OrderNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function storeName(): string
    {
        return app(SettingsService::class)->get('store.name', null, $this->order->store_id) ?: (string) config('app.name', 'Store');
    }

    protected function money(float|string $amount): string
    {
        $currency = app(SettingsService::class)->get('store.currency', 'BDT', $this->order->store_id);

        return number_format((float) $amount, 2).' '.$currency;
    }

    protected function orderUrl(): string
    {
        $base = rtrim((string) config('app.url', ''), '/');

        if ($this->order->user_id) {
            return $base.'/account/orders/'.$this->order->id;
        }

        return $base.'/order-confirmation/'.$this->order->order_number;
    }
}
