<?php

namespace App\Services\SmsGateways;

interface SmsGateway
{
    /**
     * Send an SMS message to a phone number.
     */
    public function send(string $phone, string $message): bool;

    /**
     * Get the gateway name identifier.
     */
    public function getName(): string;

    /**
     * Check if the gateway is configured and ready to send.
     */
    public function isConfigured(): bool;
}
