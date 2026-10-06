<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Scopes\BelongsToStore;
use App\Services\PaymentGateways\PaymentGateway;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
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

    /**
     * Retry payment on an existing pending order without touching the
     * (already converted) cart.
     *
     * Idempotency: the same key always resolves to the same payment
     * attempt. A completed attempt replays its stored redirect_url without
     * calling the gateway again; a failed attempt re-executes on the same
     * row; a still-pending attempt without a redirect means another
     * request is in flight and the caller gets a 409-style error. The row
     * lock serializes concurrent retries of one order; the unique
     * (order_id, idempotency_key) is the backstop.
     *
     * @return array{order: Order, payment: Payment, redirect_url: ?string, replayed: bool}
     */
    public function retryPayment(Order $order, ?string $idempotencyKey = null): array
    {
        $method = null;

        $payment = DB::transaction(function () use ($order, $idempotencyKey, &$method) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== 'pending') {
                throw new \InvalidArgumentException('Only pending orders can be retried.');
            }

            if ($locked->expires_at && $locked->expires_at->isPast()) {
                throw new \InvalidArgumentException('This order has expired. Please place a new order.');
            }

            $method = $locked->payments()->latest()->first()?->method;

            if (! $method || $method === 'cod') {
                throw new \InvalidArgumentException('This order has no retryable online payment.');
            }

            if (! PaymentGatewayFactory::isEnabled($method)) {
                throw new \InvalidArgumentException('This payment method is currently unavailable.');
            }

            if ($idempotencyKey) {
                $existing = $locked->payments()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing) {
                    if ($existing->redirect_url) {
                        return $existing;
                    }

                    // Failed attempts re-execute on the same row. So do
                    // stale pending rows (older than any gateway call can
                    // still be in flight): the server died mid-attempt and
                    // the key must not 409 forever.
                    if ($existing->status === 'failed' || $existing->created_at?->lt(now()->subMinutes(5))) {
                        $existing->update(['status' => 'pending', 'gateway_response' => null]);

                        return $existing;
                    }

                    throw new \RuntimeException(
                        'A payment attempt is already in progress. Please wait a moment and try again.',
                        409
                    );
                }
            }

            try {
                return $locked->payments()->create([
                    'method' => $method,
                    'status' => 'pending',
                    'amount' => $locked->total,
                    'gateway' => $method,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException) {
                $existing = $locked->payments()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing && $existing->redirect_url) {
                    return $existing;
                }

                throw new \RuntimeException(
                    'A payment attempt is already in progress. Please wait a moment and try again.',
                    409
                );
            }
        });

        if ($payment->redirect_url) {
            return [
                'order' => $order->fresh(),
                'payment' => $payment,
                'redirect_url' => $payment->redirect_url,
                'replayed' => true,
            ];
        }

        try {
            $result = $this->initiate($order, $payment, $method);
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed']);

            throw $e;
        }

        $payment->update(['redirect_url' => $result['redirect_url']]);

        return [
            'order' => $order->fresh(),
            'payment' => $payment->fresh(),
            'redirect_url' => $result['redirect_url'],
            'replayed' => false,
        ];
    }

    public function handleWebhook(string $method, array $payload): ?Payment
    {
        $gateway = $this->resolveGateway($method);

        $result = $gateway->processWebhook($payload);

        $gatewayTransactionId = $result['gateway_transaction_id'] ?? '';

        if (! $gatewayTransactionId) {
            return null;
        }

        // Gateway transaction ids are globally unique: never scope this
        // lookup to the resolved store, or webhooks for other stores'
        // payments resolve to nothing and orders never confirm.
        $payment = Payment::withoutGlobalScope(BelongsToStore::class)
            ->where('gateway', $gateway->getName())
            ->where('gateway_transaction_id', $gatewayTransactionId)
            ->first();

        if (! $payment) {
            $paymentId = $payload['tran_id'] ?? null;

            // tran_id-as-payment-id is an sslcommerz-only convention (its
            // gateway_transaction_id IS our payment id). For every other
            // gateway tran_id is attacker-controlled input: resolving on it
            // would let anyone cancel anyone's payment without verification.
            if ($paymentId && $gateway->getName() === 'sslcommerz') {
                $payment = Payment::withoutGlobalScope(BelongsToStore::class)
                    ->where('id', $paymentId)
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

        if ($result['status'] === 'paid' && config('payment.verify_webhooks', true)) {
            if (! $gateway->verifyPayment($payment, $payload)) {
                Log::warning('Payment webhook verification failed', [
                    'payment_id' => $payment->id,
                    'gateway' => $gateway->getName(),
                ]);

                return $payment;
            }
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

        if (! $gateway) {
            Log::warning('Refund skipped: no gateway implementation', [
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
            ]);

            return false;
        }

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
            throw new \InvalidArgumentException("Payment gateway [{$name}] is not supported.");
        }

        return $gateway;
    }
}
