<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Initiate a payment with the gateway.
     * Returns an array with redirect_url for customer-facing gateways,
     * or payment_data for direct charge gateways.
     */
    public function initiatePayment(Order $order, Payment $payment): array;

    /**
     * Verify a payment callback/response from the gateway.
     * Returns true if the payment is verified as successful.
     */
    public function verifyPayment(Payment $payment, array $payload): bool;

    /**
     * Process a webhook/IPN from the gateway.
     * Returns the payment status and gateway transaction ID.
     */
    public function processWebhook(array $payload): array;

    /**
     * Process a refund for a payment.
     */
    public function refund(Payment $payment, float $amount): bool;

    /**
     * Get the gateway name identifier.
     */
    public function getName(): string;
}
