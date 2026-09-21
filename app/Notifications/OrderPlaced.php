<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class OrderPlaced extends OrderNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Complete your payment: Order {$this->order->order_number}")
            ->greeting("Thanks for shopping with {$this->storeName()}!")
            ->line("Your order {$this->order->order_number} is placed and waiting for payment.")
            ->line('Total: '.$this->money($this->order->total))
            ->line('Complete your payment soon. Unpaid orders expire automatically.')
            ->action('View order', $this->orderUrl());
    }
}
