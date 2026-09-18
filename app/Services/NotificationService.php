<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Jobs\SendOrderSms;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Notifications\BackInStock;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderConfirmed;
use App\Notifications\OrderDelivered;
use App\Notifications\OrderNotification;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderShipped;
use App\Notifications\ReviewApproved;
use Illuminate\Notifications\AnonymousNotifiable;

class NotificationService
{
    public function __construct(
        private readonly SettingsService $settings = new SettingsService,
    ) {}

    public function isMailEnabled(?int $storeId = null): bool
    {
        return $this->flag('notifications.mail_enabled', config('notifications.mail_enabled', true), $storeId);
    }

    public function isSmsEnabled(?int $storeId = null): bool
    {
        return $this->flag('notifications.sms_enabled', config('notifications.sms_enabled', false), $storeId);
    }

    public function notifyOrderPlaced(Order $order): void
    {
        $this->sendOrderMail($order, new OrderPlaced($order));
        $this->sendOrderSms($order, "Order {$order->order_number} placed. Total {$this->money($order->total, $order->store_id)}. Complete payment soon.");
    }

    public function notifyOrderConfirmed(Order $order): void
    {
        $this->sendOrderMail($order, new OrderConfirmed($order));
        $this->sendOrderSms($order, "Order {$order->order_number} confirmed. Total {$this->money($order->total, $order->store_id)}.");
    }

    public function notifyOrderShipped(Order $order): void
    {
        $shipment = $order->shipments()->latest()->first();
        $this->sendOrderMail($order, new OrderShipped($order, $shipment));

        $suffix = $shipment?->tracking_number ? " Tracking: {$shipment->tracking_number}." : '';
        $this->sendOrderSms($order, "Order {$order->order_number} shipped.{$suffix}");
    }

    public function notifyOrderDelivered(Order $order): void
    {
        $this->sendOrderMail($order, new OrderDelivered($order));
        $this->sendOrderSms($order, "Order {$order->order_number} delivered. Enjoy!");
    }

    public function notifyOrderCancelled(Order $order, ?string $reason = null): void
    {
        $this->sendOrderMail($order, new OrderCancelled($order, $reason));
        $this->sendOrderSms($order, "Order {$order->order_number} cancelled.");
    }

    public function notifyReviewApproved(Review $review): void
    {
        if (! $this->isMailEnabled($review->product?->store_id)) {
            return;
        }

        $review->loadMissing('user', 'product');

        if (! $review->user?->email) {
            return;
        }

        $review->user->notify((new ReviewApproved($review))->afterCommit());
    }

    public function notifyBackInStock(Product $product): void
    {
        if (! $this->isMailEnabled($product->store_id)) {
            return;
        }

        $product->loadMissing('wishlists.user');

        foreach ($product->wishlists as $wishlist) {
            if ($wishlist->user?->email) {
                $wishlist->user->notify((new BackInStock($product))->afterCommit());
            }
        }
    }

    private function sendOrderMail(Order $order, OrderNotification $notification): void
    {
        if (! $this->isMailEnabled($order->store_id)) {
            return;
        }

        $recipient = $this->mailRecipient($order);

        if (! $recipient) {
            return;
        }

        $recipient->notify($notification->afterCommit());
    }

    private function sendOrderSms(Order $order, string $message): void
    {
        if (! $this->isSmsEnabled($order->store_id)) {
            return;
        }

        $phone = PhoneHelper::normalize($order->shipping_phone);

        if (! $phone) {
            return;
        }

        $store = $this->settings->get('store.name', null, $order->store_id) ?: (string) config('app.name', 'Store');

        SendOrderSms::dispatch($phone, "{$message} - {$store}", "order:{$order->id}")->afterCommit();
    }

    private function mailRecipient(Order $order): ?object
    {
        $order->loadMissing('user');

        if ($order->user?->email) {
            return $order->user;
        }

        if ($order->guest_email) {
            return (new AnonymousNotifiable)->route('mail', $order->guest_email);
        }

        return null;
    }

    private function money(float|string $amount, ?int $storeId = null): string
    {
        $currency = $this->settings->get('store.currency', 'BDT', $storeId);

        return number_format((float) $amount, 2).' '.$currency;
    }

    private function flag(string $key, mixed $default, ?int $storeId = null): bool
    {
        $raw = $this->settings->get($key, null, $storeId);

        if ($raw === null) {
            return (bool) $default;
        }

        return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
    }
}
