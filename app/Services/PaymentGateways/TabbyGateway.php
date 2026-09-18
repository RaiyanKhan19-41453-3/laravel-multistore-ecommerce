<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TabbyGateway implements PaymentGateway
{
    private string $secretKey;

    private string $publicKey;

    private string $merchantCode;

    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = (string) config('payment.gateways.tabby.secret_key', '');
        $this->publicKey = (string) config('payment.gateways.tabby.public_key', '');
        $this->merchantCode = (string) config('payment.gateways.tabby.merchant_code', '');
        $this->baseUrl = rtrim((string) config('payment.gateways.tabby.base_url', 'https://api.tabby.ai'), '/');
    }

    public function initiatePayment(Order $order, Payment $payment): array
    {
        $parameters = [
            'order_number' => $order->order_number,
            'store' => $order->store_id ? Store::find($order->store_id)?->slug : null,
        ];
        $successUrl = route('store.order-confirmation', [...$parameters, 'status' => 'success']);
        $cancelUrl = route('store.order-confirmation', [...$parameters, 'status' => 'cancel']);
        $failureUrl = route('store.order-confirmation', [...$parameters, 'status' => 'failure']);

        $payload = [
            'payment' => [
                'amount' => number_format((float) $order->total, 2, '.', ''),
                'currency' => 'SAR',
                'description' => 'Order #'.$order->order_number,
                'buyer' => [
                    'email' => $order->user?->email ?? $order->guest_email ?? 'guest@example.com',
                    'phone' => $order->shipping_phone,
                    'name' => $order->shipping_name,
                ],
                'shipping_address' => [
                    'city' => $order->shipping_city,
                    'address' => $order->shipping_address,
                    'zip' => $order->shipping_postal_code ?? '',
                    'country' => $order->shipping_country ?? 'SA',
                ],
                'order' => [
                    'reference_id' => $order->order_number,
                    'items' => $order->items->map(fn ($item) => [
                        'title' => $item->name,
                        'quantity' => $item->quantity,
                        'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                        'category' => 'general',
                    ])->toArray(),
                ],
                'buyer_history' => [
                    'registered_since' => $order->user?->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'loyalty_level' => 0,
                ],
                'order_history' => [],
            ],
            'lang' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'merchant_code' => $this->merchantCode,
            'merchant_urls' => [
                'success' => $successUrl,
                'cancel' => $cancelUrl,
                'failure' => $failureUrl,
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->secretKey,
        ])->timeout(30)->post($this->baseUrl.'/api/v2/checkout', $payload);

        $data = $response->json();

        if ($response->failed() || empty($data['id'])) {
            Log::error('Tabby create checkout failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException('Tabby payment could not be started. Please try again.');
        }

        $payment->update([
            'gateway_transaction_id' => $data['id'] ?? '',
            'gateway_response' => $data,
        ]);

        $redirectUrl = $data['configuration']['available_products']['installments'][0]['web_url']
            ?? $data['web_url']
            ?? $data['redirect_url']
            ?? '';

        return [
            'redirect_url' => $redirectUrl,
            'payment_id' => $data['id'] ?? '',
        ];
    }

    public function verifyPayment(Payment $payment, array $payload): bool
    {
        $paymentId = $payload['id'] ?? $payload['payment']['id'] ?? $payment->gateway_transaction_id ?? '';

        if (! $paymentId) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->timeout(30)->get($this->baseUrl."/api/v2/checkout/{$paymentId}");

            $data = $response->json();

            if ($response->failed() || empty($data['id'])) {
                Log::warning('Tabby verify failed: checkout lookup failed', ['payment_id' => $paymentId]);

                return false;
            }

            $status = strtolower($data['status'] ?? '');

            return in_array($status, ['authorized', 'closed'], true);
        } catch (\Exception $e) {
            Log::error('Tabby verify failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function processWebhook(array $payload): array
    {
        $id = $payload['id'] ?? $payload['payment']['id'] ?? $payload['order']['reference_id'] ?? '';
        $status = strtolower($payload['status'] ?? $payload['payment']['status'] ?? '');

        return [
            'status' => match ($status) {
                'authorized', 'closed', 'paid' => 'paid',
                'rejected', 'failed' => 'failed',
                'expired', 'cancelled' => 'cancelled',
                default => 'pending',
            },
            'gateway_transaction_id' => (string) $id,
        ];
    }

    public function refund(Payment $payment, float $amount): bool
    {
        $paymentId = $payment->gateway_transaction_id;

        if (! $paymentId) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->timeout(30)->post($this->baseUrl."/api/v2/payments/{$paymentId}/refunds", [
                'amount' => number_format($amount, 2, '.', ''),
                'reason' => 'Refund for order #'.$payment->order->order_number,
            ]);

            $data = $response->json();

            return $response->successful() && in_array(strtolower($data['status'] ?? 'refunded'), ['refunded', 'created'], true);
        } catch (\Exception $e) {
            Log::error('Tabby refund failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'tabby';
    }
}
