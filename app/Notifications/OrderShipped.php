<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Notifications\Messages\MailMessage;

class OrderShipped extends OrderNotification
{
    public function __construct(Order $order, public ?Shipment $shipment = null)
    {
        parent::__construct($order);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Order {$this->order->order_number} shipped")
            ->greeting('Your order is on its way!')
            ->line("Order {$this->order->order_number} has been handed to the courier.");

        if ($this->shipment?->tracking_number) {
            $mail->line("Tracking number: {$this->shipment->tracking_number}");
        }

        if ($this->shipment?->courier) {
            $mail->line("Courier: {$this->shipment->courier}");
        }

        return $mail->action('Track your order', $this->orderUrl());
    }
}
