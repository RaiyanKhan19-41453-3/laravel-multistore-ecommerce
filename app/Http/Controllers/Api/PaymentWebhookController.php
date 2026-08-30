<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentGateways\SSLCommerzGateway;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
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

        if ($method === 'sslcommerz' && config('payment.verify_webhooks', true)) {
            $gateway = new SSLCommerzGateway;

            if (! $gateway->verifyPayment(new Payment, $payload)) {
                Log::warning('SSLCommerz webhook signature verification failed', ['payload' => $payload]);

                return response()->json(['status' => 'error', 'message' => 'Verification failed'], 400);
            }
        }

        $payment = $this->paymentService->handleWebhook($method, $payload);

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
}
