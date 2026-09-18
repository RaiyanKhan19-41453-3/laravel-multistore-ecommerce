<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeGateway implements PaymentGateway
{
    private string $secretKey;

    private string $webhookSecret;

    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = (string) config('payment.gateways.stripe.secret_key', '');
        $this->webhookSecret = (string) config('payment.gateways.stripe.webhook_secret', '');
        $this->baseUrl = rtrim((string) config('payment.gateways.stripe.base_url', 'https://api.stripe.com'), '/');
    }

    public function initiatePayment(Order $order, Payment $payment): array
    {
        $parameters = [
            'order_number' => $order->order_number,
            'store' => $order->store_id ? Store::find($order->store_id)?->slug : null,
        ];
        $successUrl = route('store.order-confirmation', [...$parameters, 'status' => 'success']).'&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('store.order-confirmation', [...$parameters, 'status' => 'cancel']);

        $currency = strtolower((string) (config('store.currency', 'SAR') === 'BDT' ? 'BDT' : 'SAR'));
        // Stripe does not support BDT; fall back to USD for BD stores, SAR for SA
        if ($currency === 'bdt') {
            $currency = 'usd';
        }

        $payload = [
            'payment_method_types[]' => 'card',
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $order->order_number,
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][product_data][name]' => 'Order #'.$order->order_number,
            'line_items[0][price_data][unit_amount]' => (int) round((float) $order->total * 100),
            'line_items[0][quantity]' => 1,
            'metadata[order_number]' => $order->order_number,
            'metadata[payment_id]' => $payment->id,
            'customer_email' => $order->user?->email ?? $order->guest_email ?? null,
        ];

        // Remove null values
        $payload = array_filter($payload, fn ($v) => $v !== null);

        $response = Http::withBasicAuth($this->secretKey, '')
            ->asForm()
            ->timeout(30)
            ->post($this->baseUrl.'/v1/checkout/sessions', $payload);

        $data = $response->json();

        if ($response->failed() || empty($data['id'])) {
            Log::error('Stripe create session failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException('Stripe payment could not be started. Please try again.');
        }

        $payment->update([
            'gateway_transaction_id' => $data['id'] ?? '',
            'gateway_response' => $data,
        ]);

        return [
            'redirect_url' => $data['url'] ?? '',
            'session_id' => $data['id'] ?? '',
        ];
    }

    public function verifyPayment(Payment $payment, array $payload): bool
    {
        $sessionId = $payment->gateway_transaction_id ?? '';

        if (! $sessionId) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->timeout(30)
                ->get($this->baseUrl."/v1/checkout/sessions/{$sessionId}");

            $data = $response->json();

            if ($response->failed() || empty($data['id'])) {
                Log::warning('Stripe verify failed: checkout session lookup failed', ['session_id' => $sessionId]);

                return false;
            }

            return ($data['payment_status'] ?? '') === 'paid' && ($data['status'] ?? '') === 'complete';
        } catch (\Exception $e) {
            Log::error('Stripe verify failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function processWebhook(array $payload): array
    {
        // Stripe events: type = checkout.session.completed, data.object.id, data.object.payment_status
        $type = $payload['type'] ?? '';
        $object = $payload['data']['object'] ?? $payload;

        $id = $object['id'] ?? $payload['id'] ?? $payload['session_id'] ?? '';
        $paymentStatus = $object['payment_status'] ?? $object['status'] ?? $payload['status'] ?? '';

        if ($type === 'checkout.session.completed' && $paymentStatus === 'paid') {
            return [
                'status' => 'paid',
                'gateway_transaction_id' => (string) $id,
            ];
        }

        if (str_contains($type, 'failed') || $paymentStatus === 'failed') {
            return [
                'status' => 'failed',
                'gateway_transaction_id' => (string) $id,
            ];
        }

        if (str_contains($type, 'expired') || $paymentStatus === 'expired' || $paymentStatus === 'cancelled') {
            return [
                'status' => 'cancelled',
                'gateway_transaction_id' => (string) $id,
            ];
        }

        $status = strtolower($paymentStatus);

        return [
            'status' => match ($status) {
                'paid', 'complete' => 'paid',
                'failed' => 'failed',
                'cancelled', 'expired' => 'cancelled',
                default => 'pending',
            },
            'gateway_transaction_id' => (string) $id,
        ];
    }

    public function refund(Payment $payment, float $amount): bool
    {
        // Need payment_intent from session
        $sessionId = $payment->gateway_transaction_id;

        if (! $sessionId) {
            return false;
        }

        try {
            // Fetch session to get payment_intent
            $sessionResponse = Http::withBasicAuth($this->secretKey, '')
                ->timeout(15)
                ->get($this->baseUrl."/v1/checkout/sessions/{$sessionId}");

            $sessionData = $sessionResponse->json();
            $paymentIntent = $sessionData['payment_intent'] ?? $sessionId;

            $response = Http::withBasicAuth($this->secretKey, '')
                ->asForm()
                ->timeout(30)
                ->post($this->baseUrl.'/v1/refunds', [
                    'payment_intent' => $paymentIntent,
                    'amount' => (int) round($amount * 100),
                    'reason' => 'requested_by_customer',
                ]);

            $data = $response->json();

            return $response->successful() && in_array($data['status'] ?? '', ['succeeded', 'pending'], true);
        } catch (\Exception $e) {
            Log::error('Stripe refund failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'stripe';
    }
}
