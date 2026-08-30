<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SSLCommerzGateway implements PaymentGateway
{
    private string $storeId;

    private string $storePassword;

    private bool $sandbox;

    private string $baseUrl;

    public function __construct()
    {
        $this->storeId = (string) config('payment.gateways.sslcommerz.store_id', '');
        $this->storePassword = (string) config('payment.gateways.sslcommerz.store_password', '');
        $this->sandbox = (bool) config('payment.gateways.sslcommerz.sandbox', true);
        $this->baseUrl = $this->sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    public function initiatePayment(Order $order, Payment $payment): array
    {
        $webhookUrl = route('payments.webhook.sslcommerz', [
            'method' => 'sslcommerz',
            'order' => $order->order_number,
        ]);

        $payload = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'total_amount' => $order->total,
            'currency' => 'BDT',
            'tran_id' => $payment->id,
            'success_url' => $webhookUrl.'?type=success',
            'fail_url' => $webhookUrl.'?type=fail',
            'cancel_url' => $webhookUrl.'?type=cancel',
            'ipn_url' => $webhookUrl,
            'product_name' => 'Order #'.$order->order_number,
            'product_category' => 'E-commerce',
            'product_profile' => 'general',
            'cus_name' => $order->shipping_name,
            'cus_email' => $order->user->email,
            'cus_add1' => $order->shipping_address,
            'cus_city' => $order->shipping_city,
            'cus_state' => $order->shipping_state,
            'cus_postcode' => $order->shipping_postal_code ?? '',
            'cus_country' => $order->shipping_country,
            'cus_phone' => $order->shipping_phone,
            'shipping_method' => 'NO',
        ];

        $response = Http::timeout(30)
            ->post($this->baseUrl.'/gwprocess/v4/api.php', $payload);

        $data = $response->json();

        if (! is_array($data) || ($data['status'] ?? '') !== 'SUCCESS') {
            Log::error('SSLCommerz initiation failed', ['response' => $data]);

            throw new \RuntimeException('Payment gateway initiation failed: '.($data['failedreason'] ?? 'Unknown error'));
        }

        return [
            'redirect_url' => $data['GatewayPageURL'] ?? '',
            'session_key' => $data['sessionkey'] ?? '',
        ];
    }

    public function verifyPayment(Payment $payment, array $payload): bool
    {
        $valId = $payload['val_id'] ?? '';

        if (! $valId) {
            return false;
        }

        try {
            $response = Http::timeout(30)->post($this->baseUrl.'/validator/api/validationserver.php', [
                'val_id' => $valId,
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePassword,
            ]);

            $data = $response->json();

            return ($data['status'] ?? '') === 'VALID';
        } catch (ConnectionException $e) {
            Log::error('SSLCommerz verification connection failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function processWebhook(array $payload): array
    {
        $status = $payload['status'] ?? '';
        $transactionId = $payload['tran_id'] ?? '';

        return [
            'status' => match (strtoupper($status)) {
                'VALID', 'SUCCESS' => 'paid',
                'FAILED' => 'failed',
                'CANCELLED' => 'cancelled',
                default => 'pending',
            },
            'gateway_transaction_id' => (string) $transactionId,
        ];
    }

    public function refund(Payment $payment, float $amount): bool
    {
        try {
            $response = Http::timeout(30)->post($this->baseUrl.'/validator/api/refund.php', [
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePassword,
                'refund_amount' => $amount,
                'tran_id' => $payment->gateway_transaction_id,
                'remarks' => 'Refund for order #'.$payment->order->order_number,
            ]);

            $data = $response->json();

            return ($data['status'] ?? '') === 'success';
        } catch (ConnectionException $e) {
            Log::error('SSLCommerz refund connection failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'sslcommerz';
    }
}
