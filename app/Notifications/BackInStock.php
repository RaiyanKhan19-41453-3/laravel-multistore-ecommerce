<?php

namespace App\Notifications;

use App\Models\Product;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class BackInStock extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $store = app(SettingsService::class)->get('store.name', null, $this->product->store_id) ?: (string) config('app.name', 'Store');
        $name = $this->product->displayName();
        $url = rtrim((string) config('app.url', ''), '/').'/products/'.$this->product->slug;

        return (new MailMessage)
            ->subject("Back in stock: {$name}")
            ->greeting("Good news from {$store}!")
            ->line("{$name} is back in stock. It may sell out again soon.")
            ->action('Shop now', $url);
    }
}
