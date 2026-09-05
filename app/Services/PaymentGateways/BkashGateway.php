<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BkashGateway implements PaymentGateway
{
    private string $appKey;

    private string $appSecret;

    private string $username;

    private string $password;

    private bool $sandbox;

    private string $baseUrl;

    public function __construct()
    {
        $this->appKey = (string) config('payment.gateways.bkash.app_key', '');
        $this->appSecret = (string) config('payment.gateways.bkash.app_secret', '');
        $this->username = (string) config('payment.gateways.bkash.username', '');
        $this->password = (string) config('payment.gateways.bkash.password', '');
        $this->sandbox = (bool) config('payment.gateways.bkash.sandbox', true);
        $this->baseUrl = $this->sandbox
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta';
    }

    public function initiatePayment(Order $order, Payment $payment): array
    {
        $idToken = $this->getAccessToken();

        $callbackUrl = route('payments.callback.bkash', [
            'order' => $order->order_number,
        ]);

        $payload = [
            'mode' => '0001',
            'payerReference' => $order->shipping_phone,
            'callbackURL' => $callbackUrl,
            'amount' => (string) $order->total,
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $order->order_number,
        ];

        $response = Http::withHeaders([
            'Authorization' => $idToken,
            'X-App-Key' => $this->appKey,
        ])->timeout(30)->post("{$this->baseUrl}/tokenized/checkout/create", $payload);

        $data = $response->json();

        if ($response->failed() || ($data['statusCode'] ?? '') !== '0000') {
            Log::error('bKash create payment failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException(
                $data['statusMessage'] ?? 'bKash API error: '.$response->body()
            );
        }

        $payment->update([
            'gateway_transaction_id' => $data['paymentID'] ?? '',
            'gateway_response' => $data,
        ]);

        return [
            'redirect_url' => $data['bkashURL'] ?? '',
            'payment_id' => $data['paymentID'] ?? '',
        ];
    }

    public function verifyPayment(Payment $payment, array $payload): bool
    {
        $paymentId = $payload['paymentID'] ?? $payment->gateway_transaction_id ?? '';

        if (! $paymentId) {
            return false;
        }

        try {
            $idToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => $idToken,
                'X-App-Key' => $this->appKey,
            ])->timeout(30)->post("{$this->baseUrl}/tokenized/checkout/execute/{$paymentId}");

            $data = $response->json();

            return ($data['statusCode'] ?? '') === '0000'
                && ($data['transactionStatus'] ?? '') === 'Completed';
        } catch (\Exception $e) {
            Log::error('bKash verify (execute) failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function queryPaymentStatus(Payment $payment): bool
    {
        $paymentId = $payment->gateway_transaction_id ?? '';

        if (! $paymentId) {
            return false;
        }

        try {
            $idToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => $idToken,
                'X-App-Key' => $this->appKey,
            ])->timeout(30)->post("{$this->baseUrl}/tokenized/checkout/query", [
                'paymentID' => $paymentId,
            ]);

            $data = $response->json();

            return ($data['statusCode'] ?? '') === '0000'
                && ($data['transactionStatus'] ?? '') === 'Completed';
        } catch (\Exception $e) {
            Log::error('bKash query payment failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function processWebhook(array $payload): array
    {
        $paymentId = $payload['paymentID'] ?? '';
        $status = $payload['status'] ?? '';

        return [
            'status' => match ($status) {
                'success' => 'paid',
                'failure' => 'failed',
                'cancel' => 'cancelled',
                default => 'pending',
            },
            'gateway_transaction_id' => $paymentId,
        ];
    }

    public function refund(Payment $payment, float $amount): bool
    {
        try {
            $idToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => $idToken,
                'X-App-Key' => $this->appKey,
            ])->timeout(30)->post("{$this->baseUrl}/tokenized/checkout/payment/refund", [
                'paymentID' => $payment->gateway_transaction_id,
                'amount' => (string) $amount,
                'reason' => 'Refund for order #'.$payment->order->order_number,
            ]);

            $data = $response->json();

            return ($data['statusCode'] ?? '') === '0000';
        } catch (\Exception $e) {
            Log::error('bKash refund failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'bkash';
    }

    private function getAccessToken(): string
    {
        $cacheKey = 'bkash_access_token';
        $token = Cache::get($cacheKey);

        if ($token) {
            return $token;
        }

        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout(30)
            ->post("{$this->baseUrl}/tokenized/checkout/token/grant", [
                'app_key' => $this->appKey,
                'app_secret' => $this->appSecret,
            ]);

        $data = $response->json();

        if ($response->failed() || empty($data['id_token'])) {
            throw new \RuntimeException(
                'bKash token grant failed: '.($data['errorMessage'] ?? $response->body())
            );
        }

        $idToken = $data['id_token'];
        $expiresIn = $data['expires_in'] ?? 3600;

        Cache::put($cacheKey, $idToken, $expiresIn - 60);

        return $idToken;
    }
}
