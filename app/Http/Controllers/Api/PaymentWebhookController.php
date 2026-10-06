<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Scopes\BelongsToStore;
use App\Services\OrderService;
use App\Services\PaymentGateways\BkashGateway;
use App\Services\PaymentGateways\MoyasarGateway;
use App\Services\PaymentService;
use App\Support\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected OrderService $orderService,
    ) {}

    public function handle(Request $request, string $method): JsonResponse|RedirectResponse
    {
        $payload = $request->all();

        try {
            $payment = $method === 'bkash'
                ? $this->handleBkashWebhook($payload)
                : $this->paymentService->handleWebhook($method, $payload);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Payment webhook for unsupported method', ['method' => $method]);

            return response()->json(['status' => 'ignored'], 422);
        }

        if (! $payment) {
            return response()->json(['status' => 'error'], 404);
        }

        $this->loadOrder($payment);

        if ($payment->isPaid() && $payment->order->status === 'pending') {
            $this->orderService->confirmPayment($payment->order, $payment);
        } elseif ($payment->isPaid()) {
            // Late gateway retries can confirm payment after the order
            // expired or was cancelled (stock already released). Never
            // silently swallow paid money: flag it for manual review.
            Log::warning('Payment received for non-pending order; manual review required', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order->id,
                'order_number' => $payment->order->order_number,
                'order_status' => $payment->order->status,
                'amount' => $payment->amount,
                'gateway' => $payment->gateway,
            ]);
        }

        if (in_array($payment->status, ['failed', 'cancelled']) && $payment->order->status === 'pending') {
            $this->orderService->handlePaymentFailure($payment->order);
        }

        // The gateway points the customer's browser at success/fail/cancel
        // URLs (all this route, with ?type=...) but calls ipn_url
        // server-to-server. Only the browser gets a redirect; the IPN keeps
        // its JSON ack. The outcome comes from the verified payment status,
        // never from the ?type= query value.
        if ($request->query('type') && $payment->order) {
            $outcome = match ($payment->status) {
                'paid' => 'success',
                'failed' => 'failed',
                'cancelled' => 'cancelled',
                default => 'pending',
            };

            return redirect('/order-confirmation/'.$payment->order->order_number.'?payment='.$method.'_'.$outcome);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleBkashWebhook(array $payload): ?Payment
    {
        $gateway = new BkashGateway;
        $paymentId = $payload['paymentID'] ?? '';

        if (! $paymentId) {
            return null;
        }

        $payment = Payment::withoutGlobalScope(BelongsToStore::class)
            ->where('gateway', 'bkash')
            ->where('gateway_transaction_id', $paymentId)
            ->first();

        if (! $payment) {
            Log::warning('bKash webhook received for unknown payment', ['paymentID' => $paymentId]);

            return null;
        }

        $this->loadOrder($payment);

        if ($payment->isPaid()) {
            return $payment;
        }

        $verified = $gateway->verifyPayment($payment, $payload);

        if (! $verified) {
            Log::warning('bKash webhook execute failed', ['payment_id' => $payment->id]);

            return $payment;
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'gateway_response' => $payload,
        ]);

        return $payment;
    }

    public function handleBkashCallback(Request $request): RedirectResponse
    {
        $status = $request->query('status');
        $paymentId = $request->query('paymentID');
        $orderNumber = $request->query('order');

        if (! $paymentId || ! $orderNumber) {
            return redirect('/checkout?error=bkash_missing_params');
        }

        try {
            $payment = Payment::withoutGlobalScope(BelongsToStore::class)
                ->where('gateway', 'bkash')
                ->where('gateway_transaction_id', $paymentId)
                ->first();

            if (! $payment) {
                return redirect('/checkout?error=bkash_payment_not_found');
            }

            $this->loadOrder($payment);

            if ($payment->order->order_number !== $orderNumber) {
                Log::warning('bKash callback order mismatch', ['payment_id' => $payment->id]);

                return redirect('/checkout?error=bkash_payment_not_found');
            }

            if ($payment->isPaid()) {
                return redirect('/order-confirmation/'.$payment->order->order_number.'?payment=bkash_success');
            }

            if ($status === 'success') {
                $gateway = new BkashGateway;
                $verified = $gateway->queryPaymentStatus($payment);

                if ($verified) {
                    $payment->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);

                    if ($payment->order->status === 'pending') {
                        $this->orderService->confirmPayment($payment->order, $payment);
                    }

                    return redirect('/order-confirmation/'.$payment->order->order_number.'?payment=bkash_success');
                }

                return redirect('/order-confirmation/'.$payment->order->order_number.'?payment=bkash_pending');
            }

            if (in_array($status, ['failure', 'cancel'])) {
                $payment->update(['status' => $status === 'cancel' ? 'cancelled' : 'failed']);

                if ($payment->order->status === 'pending') {
                    $this->orderService->handlePaymentFailure($payment->order);
                }

                return redirect('/order-confirmation/'.$payment->order->order_number.'?payment=bkash_'.$status);
            }

            return redirect('/checkout?error=bkash_unknown_status');
        } catch (\Exception $e) {
            Log::error('bKash callback error', ['error' => $e->getMessage()]);

            return redirect('/checkout?error=bkash_callback_error');
        }
    }

    public function handleMoyasarCallback(Request $request): RedirectResponse
    {
        $paymentId = $request->query('id');

        if (! $paymentId) {
            return redirect('/checkout?error=moyasar_missing_params');
        }

        try {
            $payment = Payment::withoutGlobalScope(BelongsToStore::class)
                ->where('gateway', 'moyasar')
                ->where('gateway_transaction_id', $paymentId)
                ->first();

            if (! $payment) {
                return redirect('/checkout?error=moyasar_payment_not_found');
            }

            $this->loadOrder($payment);

            if ($payment->isPaid()) {
                return redirect('/order-confirmation/'.$payment->order->order_number.'?payment=moyasar_success');
            }

            $gateway = new MoyasarGateway;
            $verified = $gateway->verifyPayment($payment, ['id' => $paymentId]);

            if ($verified) {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                if ($payment->order->status === 'pending') {
                    $this->orderService->confirmPayment($payment->order, $payment);
                }

                return redirect('/order-confirmation/'.$payment->order->order_number.'?payment=moyasar_success');
            }

            return redirect('/order-confirmation/'.$payment->order->order_number.'?payment=moyasar_pending');
        } catch (\Exception $e) {
            Log::error('Moyasar callback error', ['error' => $e->getMessage()]);

            return redirect('/checkout?error=moyasar_callback_error');
        }
    }

    /**
     * Hydrate the payment's order bypassing the ambient store scope, then
     * act in the order's store context from here on: inventory, discounts,
     * and notifications all resolve their store ambiently, and the resolved
     * request store is unrelated to a webhook's globally-unique ids.
     */
    private function loadOrder(Payment $payment): void
    {
        $order = Order::withoutGlobalScope(BelongsToStore::class)->find($payment->order_id);
        $payment->setRelation('order', $order);

        if ($order) {
            app(CurrentStore::class)->set($order->store_id ? Store::find($order->store_id) : null);
        }
    }
}
