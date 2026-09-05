<?php

namespace App\Services\Couriers\Gateways;

use App\Models\Order;
use App\Models\Shipment;

interface CourierGateway
{
    /**
     * Create a shipment with the courier.
     *
     * @return array{consignment_id: string, tracking_number: string, delivery_fee: float, raw: array}
     */
    public function createShipment(Order $order, Shipment $shipment): array;

    /**
     * Track a shipment by tracking number.
     *
     * @return array{status: string, raw: array}
     */
    public function trackShipment(string $trackingNumber): array;

    /**
     * Cancel a shipment with the courier.
     */
    public function cancelShipment(string $courierOrderId): bool;

    /**
     * Test the API connection.
     */
    public function testConnection(): bool;

    /**
     * Return the settings schema for this gateway.
     *
     * @return array<array{key: string, label: string, type: string, required: bool, placeholder?: string}>
     */
    public static function settingsSchema(): array;
}
