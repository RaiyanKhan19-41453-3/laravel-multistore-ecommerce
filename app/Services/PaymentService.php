<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentGateways\PaymentGateway;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentGateways\SSLCommerzGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private ?PaymentGateway $gateway = null;

    public function initiate(Order $order, Payment $payment, ?string $paymentMethod = null): array
    {
        $gateway = $this->resolveGateway($paymentMethod);

        try {
            $result = $gateway->initiatePayment($order, $payment);

            return [
                'payment_id' => $payment->id,
                'redirect_url' => $result['redirect_url'] ?? null,
                'session_key' => $result['session_key'] ?? null,
            ];
        } catch (ConnectionException $e) {
            Log::error('Payment gateway connection failed', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $payment->update([
                'gateway_response' => ['error' => 'Gateway connection failed'],
            ]);

            throw new \RuntimeException('Payment gateway is currently unavailable. Please try again later.');
        } catch (\RuntimeException $e) {
            Log::error('Payment initiation failed', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $payment->update([
                'gateway_response' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    public function handleWebhook(string $method, array $payload): ?Payment
    {
        $gateway = $this->resolveGateway($method);

        $result = $gateway->processWebhook($payload);

        $gatewayTransactionId = $result['gateway_transaction_id'] ?? '';

        if (! $gatewayTransactionId) {
            return null;
        }

        $payment = Payment::where('gateway', $gateway->getName())
            ->where('gateway_transaction_id', $gatewayTransactionId)
            ->first();

        if (! $payment) {
            $paymentId = $payload['tran_id'] ?? null;

            if ($paymentId) {
                $payment = Payment::where('id', $paymentId)
                    ->where('gateway', $gateway->getName())
                    ->first();
            }
        }

        if (! $payment) {
            Log::warning('Webhook received for unknown payment', ['payload' => $payload]);

            return null;
        }

        if ($payment->isPaid()) {
            return $payment;
        }

        $payment->update([
            'status' => $result['status'],
            'gateway_transaction_id' => $gatewayTransactionId,
            'gateway_response' => $payload,
            'paid_at' => $result['status'] === 'paid' ? now() : null,
        ]);

        return $payment;
    }

    public function refund(Payment $payment, float $amount): bool
    {
        if (! $payment->isPaid()) {
            return false;
        }

        $gateway = $this->resolveGateway($payment->gateway);

        $result = $gateway->refund($payment, $amount);

        if ($result) {
            $payment->update([
                'status' => $amount >= $payment->amount ? 'refunded' : 'partially_refunded',
            ]);
        }

        return $result;
    }

    public function getGateway(?string $paymentMethod = null): PaymentGateway
    {
        return $this->resolveGateway($paymentMethod);
    }

    private function resolveGateway(?string $paymentMethod = null): PaymentGateway
    {
        $name = $paymentMethod ?? config('payment.default', 'sslcommerz');

        $gateway = PaymentGatewayFactory::make($name);

        if (! $gateway) {
            return app(SSLCommerzGateway::class);
        }

        return $gateway;
    }
}
