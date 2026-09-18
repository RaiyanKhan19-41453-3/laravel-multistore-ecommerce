<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Wishlist;
use App\Notifications\BackInStock;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderConfirmed;
use App\Notifications\OrderDelivered;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderShipped;
use App\Notifications\ReviewApproved;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function enableNotificationsTestSms(string $gateway = 'twilio'): void
{
    app(SettingsService::class)->set('notifications.sms_enabled', '1', 'notifications');

    config(['sms.default' => $gateway]);
    config(['sms.enabled' => [$gateway => true]]);
    config(['sms.gateways.twilio' => [
        'account_sid' => 'AC123',
        'auth_token' => 'auth_token_123',
        'from_number' => '+1234567890',
    ]]);
}

it('sends OrderConfirmed mail when a COD order is created from cart', function () {
    Notification::fake();

    $user = createUser();
    $product = createProduct();
    $cart = createCartWithItem($user, $product);

    $order = app(OrderService::class)->createFromCart($cart, $user, array_merge(shippingData(), ['delivery_phone' => null]), 'cod');

    expect($order->status)->toBe('confirmed');
    Notification::assertSentTo($user, OrderConfirmed::class);
});

it('sends OrderPlaced mail on demand for a guest online order', function () {
    Notification::fake();

    $user = createUser();
    $product = createProduct();
    $cart = createCartWithItem($user, $product);

    $order = app(OrderService::class)->createFromCart(
        $cart,
        null,
        array_merge(shippingData(), ['delivery_phone' => null, 'guest_email' => 'guest-placed@example.com']),
        'sslcommerz'
    );

    expect($order->status)->toBe('pending');
    Notification::assertSentOnDemand(OrderPlaced::class);
});

it('sends OrderConfirmed mail when payment is confirmed', function () {
    Notification::fake();

    $user = createUser();
    $order = Order::factory()->pending()->for($user)->create();
    $payment = Payment::factory()->for($order)->create();

    app(OrderService::class)->confirmPayment($order, $payment);

    Notification::assertSentTo($user, OrderConfirmed::class);
});

it('does not resend OrderConfirmed mail when payment confirmation repeats', function () {
    Notification::fake();

    $user = createUser();
    $order = Order::factory()->pending()->for($user)->create();
    $payment = Payment::factory()->for($order)->create();
    $service = app(OrderService::class);

    $service->confirmPayment($order, $payment);
    $service->confirmPayment($order->fresh(), $payment);

    Notification::assertSentTimes(OrderConfirmed::class, 1);
});

it('sends OrderShipped then OrderDelivered mail on status transitions', function () {
    Notification::fake();

    $user = createUser();
    $order = Order::factory()->confirmed()->for($user)->create();
    $service = app(OrderService::class);

    $service->updateStatus($order, 'processing');
    Notification::assertNothingSent();

    $service->updateStatus($order, 'shipped');
    Notification::assertSentTo($user, OrderShipped::class);

    $service->updateStatus($order, 'delivered');
    Notification::assertSentTo($user, OrderDelivered::class);
});

it('sends OrderCancelled mail with the reason on cancel', function () {
    Notification::fake();

    $user = createUser();
    $order = Order::factory()->confirmed()->for($user)->create();

    app(OrderService::class)->cancel($order, 'Changed my mind');

    Notification::assertSentTo($user, OrderCancelled::class, function (OrderCancelled $notification) use ($order) {
        return $notification->reason === 'Changed my mind'
            && $notification->order->id === $order->id;
    });
});

it('sends ReviewApproved mail when admin approves a pending review', function () {
    Notification::fake();

    $admin = createAdmin();
    $review = Review::factory()->pending()->create();

    $this->actingAs($admin)->post("/admin/reviews/{$review->id}/approve")->assertRedirect();

    Notification::assertSentTo($review->user, ReviewApproved::class);
});

it('does not resend ReviewApproved mail when approving twice', function () {
    Notification::fake();

    $admin = createAdmin();
    $review = Review::factory()->create(['is_approved' => true]);

    $this->actingAs($admin)->post("/admin/reviews/{$review->id}/approve")->assertRedirect();

    Notification::assertNothingSent();
});

it('sends BackInStock mail to wishlisters when restocked', function () {
    Notification::fake();

    $product = createProduct(stock: 0);
    $wisher = createUser();
    Wishlist::factory()->create(['user_id' => $wisher->id, 'product_id' => $product->id]);

    $inventory = app(InventoryService::class)->getOrCreateForProduct($product);
    app(InventoryService::class)->setQuantity($inventory, 5);

    Notification::assertSentTo($wisher, BackInStock::class);
});

it('sends no restock mail when stock was already available', function () {
    Notification::fake();

    $product = createProduct(stock: 50);
    $wisher = createUser();
    Wishlist::factory()->create(['user_id' => $wisher->id, 'product_id' => $product->id]);

    $inventory = app(InventoryService::class)->getOrCreateForProduct($product);
    app(InventoryService::class)->setQuantity($inventory, 60);

    Notification::assertNothingSent();
});

it('sends nothing when mail notifications are disabled', function () {
    Notification::fake();
    app(SettingsService::class)->set('notifications.mail_enabled', '0', 'notifications');

    $user = createUser();
    $order = Order::factory()->pending()->for($user)->create();
    $payment = Payment::factory()->for($order)->create();

    app(OrderService::class)->confirmPayment($order, $payment);

    Notification::assertNothingSent();
});

it('dispatches order SMS when enabled with a configured gateway', function () {
    Notification::fake();
    Http::fake();
    enableNotificationsTestSms();

    $user = createUser();
    $order = Order::factory()->pending()->for($user)->create([
        'shipping_phone' => '01712345678',
    ]);
    $payment = Payment::factory()->for($order)->create();

    app(OrderService::class)->confirmPayment($order, $payment);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.twilio.com')
        && str_contains((string) $request['Body'], $order->order_number));
});

it('skips SMS silently when no gateway is configured', function () {
    Notification::fake();
    Http::fake();
    app(SettingsService::class)->set('notifications.sms_enabled', '1', 'notifications');

    $user = createUser();
    $order = Order::factory()->pending()->for($user)->create([
        'shipping_phone' => '01712345678',
    ]);
    $payment = Payment::factory()->for($order)->create();

    app(OrderService::class)->confirmPayment($order, $payment);

    Http::assertNothingSent();
    Notification::assertSentTo($user, OrderConfirmed::class);
});

it('renders the confirmed mail with the order number in the subject', function () {
    $order = Order::factory()->confirmed()->create();

    $mail = (new OrderConfirmed($order))->toMail($order->user);

    expect($mail->subject)->toContain($order->order_number);
});

it('renders the cancellation reason in the cancelled mail', function () {
    $order = Order::factory()->cancelled()->create();

    $mail = (new OrderCancelled($order, 'Out of stock'))->toMail($order->user);

    expect(implode("\n", $mail->introLines))->toContain('Out of stock');
});
