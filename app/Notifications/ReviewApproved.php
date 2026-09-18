<?php

namespace App\Notifications;

use App\Models\Review;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class ReviewApproved extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Review $review) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $store = app(SettingsService::class)->get('store.name', null, $this->review->product?->store_id) ?: (string) config('app.name', 'Store');
        $productName = $this->review->product?->displayName() ?? 'the product';
        $url = rtrim((string) config('app.url', ''), '/').'/products/'.($this->review->product?->slug ?? '');

        return (new MailMessage)
            ->subject('Your review is live')
            ->greeting("Thanks for reviewing {$productName}!")
            ->line("Your {$this->review->rating}-star review is now visible on {$store}.")
            ->action('View product', $url);
    }
}
