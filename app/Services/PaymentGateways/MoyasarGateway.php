<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoyasarGateway implements PaymentGateway
{
    private string $apiKey;

    private string $publishableKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('payment.gateways.moyasar.api_key', '');
        $this->publishableKey = (string) config('payment.gateways.moyasar.publishable_key', '');
        $this->baseUrl = rtrim((string) config('payment.gateways.moyasar.base_url', 'https://api.moyasar.com'), '/');
    }

    public function initiatePayment(Order $order, Payment $payment): array
    {
        $callbackUrl = route('payments.callback.moyasar');

        $payload = [
            'amount' => (int) round((float) $order->total * 100),
            'currency' => 'SAR',
            'description' => 'Order #'.$order->order_number,
            'callback_url' => $callbackUrl,
            'source' => ['type' => 'creditcard'],
            'metadata' => [
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
            ],
        ];

        $response = Http::withBasicAuth($this->apiKey, '')
            ->timeout(30)
            ->post($this->baseUrl.'/v1/payments', $payload);

        $data = $response->json();

        if ($response->failed() || empty($data['id'])) {
            Log::error('Moyasar initiation failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException('Moyasar payment could not be started. Please try again.');
        }

        $payment->update([
            'gateway_transaction_id' => $data['id'] ?? '',
            'gateway_response' => $data,
        ]);

        return [
            'redirect_url' => $data['url'] ?? $data['source']['transaction_url'] ?? '',
            'payment_id' => $data['id'] ?? '',
        ];
    }

    public function verifyPayment(Payment $payment, array $payload): bool
    {
        $paymentId = $payload['id'] ?? $payment->gateway_transaction_id ?? '';

        if (! $paymentId) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->timeout(30)
                ->get($this->baseUrl."/v1/payments/{$paymentId}");

            $data = $response->json();

            if ($response->failed() || empty($data['id'])) {
                return false;
            }

            $status = strtolower($data['status'] ?? '');

            return $status === 'paid' && (int) ($data['amount'] ?? 0) === (int) round((float) $payment->amount * 100);
        } catch (\Exception $e) {
            Log::error('Moyasar verify failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function processWebhook(array $payload): array
    {
        $id = $payload['id'] ?? $payload['data']['id'] ?? '';
        $status = strtolower($payload['status'] ?? $payload['data']['status'] ?? '');

        return [
            'status' => match ($status) {
                'paid' => 'paid',
                'failed' => 'failed',
                'authorized' => 'paid',
                'cancelled' => 'cancelled',
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
            $response = Http::withBasicAuth($this->apiKey, '')
                ->timeout(30)
                ->post($this->baseUrl."/v1/payments/{$paymentId}/refund", [
                    'amount' => (int) round($amount * 100),
                ]);

            $data = $response->json();

            return $response->successful() && in_array(strtolower($data['status'] ?? ''), ['refunded', 'paid'], true);
        } catch (\Exception $e) {
            Log::error('Moyasar refund failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'moyasar';
    }
}
