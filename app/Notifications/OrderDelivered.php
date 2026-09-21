<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class OrderDelivered extends OrderNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order {$this->order->order_number} delivered")
            ->greeting('Delivered, enjoy!')
            ->line("Order {$this->order->order_number} has been delivered. Thanks for shopping with {$this->storeName()}.")
            ->action('View order', $this->orderUrl());
    }
}
