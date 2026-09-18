<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class OrderConfirmed extends OrderNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order {$this->order->order_number} confirmed")
            ->greeting("Good news from {$this->storeName()}!")
            ->line("Your order {$this->order->order_number} is confirmed and being prepared.")
            ->line('Total: '.$this->money($this->order->total))
            ->action('Track your order', $this->orderUrl());
    }
}
