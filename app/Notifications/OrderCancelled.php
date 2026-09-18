<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;

class OrderCancelled extends OrderNotification
{
    public function __construct(Order $order, public ?string $reason = null)
    {
        parent::__construct($order);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Order {$this->order->order_number} cancelled")
            ->greeting('Your order was cancelled')
            ->line("Order {$this->order->order_number} has been cancelled.");

        if ($this->reason) {
            $mail->line("Reason: {$this->reason}");
        }

        return $mail->line("If you already paid, contact {$this->storeName()} support for help.");
    }
}
