<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentGateways\BkashGateway;
use App\Services\PaymentService;
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

    public function handle(Request $request, string $method): JsonResponse
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

        if ($payment->isPaid() && $payment->order->status === 'pending') {
            $this->orderService->confirmPayment($payment->order, $payment);
        }

        if (in_array($payment->status, ['failed', 'cancelled']) && $payment->order->status === 'pending') {
            $this->orderService->handlePaymentFailure($payment->order);
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

        $payment = Payment::where('gateway', 'bkash')
            ->where('gateway_transaction_id', $paymentId)
            ->first();

        if (! $payment) {
            Log::warning('bKash webhook received for unknown payment', ['paymentID' => $paymentId]);

            return null;
        }

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
            $payment = Payment::where('gateway', 'bkash')
                ->where('gateway_transaction_id', $paymentId)
                ->first();

            if (! $payment) {
                return redirect('/checkout?error=bkash_payment_not_found');
            }

            if ($payment->order->order_number !== $orderNumber) {
                Log::warning('bKash callback order mismatch', ['payment_id' => $payment->id]);

                return redirect('/checkout?error=bkash_payment_not_found');
            }

            if ($payment->isPaid()) {
                return redirect('/account/orders/'.$payment->order->order_number.'?payment=bkash_success');
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

                    return redirect('/account/orders/'.$payment->order->order_number.'?payment=bkash_success');
                }

                return redirect('/account/orders/'.$payment->order->order_number.'?payment=bkash_pending');
            }

            if (in_array($status, ['failure', 'cancel'])) {
                $payment->update(['status' => $status === 'cancel' ? 'cancelled' : 'failed']);

                if ($payment->order->status === 'pending') {
                    $this->orderService->handlePaymentFailure($payment->order);
                }

                return redirect('/account/orders/'.$payment->order->order_number.'?payment=bkash_'.$status);
            }

            return redirect('/checkout?error=bkash_unknown_status');
        } catch (\Exception $e) {
            Log::error('bKash callback error', ['error' => $e->getMessage()]);

            return redirect('/checkout?error=bkash_callback_error');
        }
    }
}
