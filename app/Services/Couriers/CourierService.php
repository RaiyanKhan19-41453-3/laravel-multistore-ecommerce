<?php

namespace App\Services\Couriers;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\OrderService;
use Illuminate\Support\Facades\Log;

class CourierService
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    /**
     * Send an order to the courier API.
     */
    public function sendToCourier(Order $order, string $courierCode, string $courierName): Shipment
    {
        $gateway = CourierGatewayFactory::make($courierCode);

        if (! $gateway) {
            throw new \RuntimeException("Courier '{$courierName}' is not configured or does not support API integration.");
        }

        // Gate before creating anything: dispatching an unpaid (or
        // finished) order would book a real consignment at the courier
        // and then fail the state machine, orphaning the booking.
        if (! $this->orderService->isShippable($order)) {
            throw new \InvalidArgumentException(
                "Only confirmed or processing orders can be sent to a courier. Current status: '{$order->status}'."
            );
        }

        $shipment = $order->shipments()->create([
            'courier_code' => $courierCode,
            'courier' => $courierName,
            'status' => 'pending',
        ]);

        try {
            $result = $gateway->createShipment($order, $shipment);

            $shipment->update([
                'courier_order_id' => $result['consignment_id'],
                'tracking_number' => $result['tracking_number'] ?? $result['consignment_id'],
                'shipping_cost' => $result['delivery_fee'] ?? null,
                'courier_response' => $result['raw'],
                'status' => 'pending',
            ]);

            // Fulfillment moves go through the order state machine: sending
            // a pending, cancelled, or delivered order must fail loudly
            // instead of resurrecting it as shipped.
            $this->orderService->markShipped($order);
            $order->update(['fulfillment_type' => 'courier']);

            Log::info('Order sent to courier', [
                'order_id' => $order->id,
                'courier' => $courierName,
                'consignment_id' => $result['consignment_id'],
            ]);

            return $shipment->fresh();
        } catch (\Exception $e) {
            $shipment->update([
                'status' => 'failed',
                'note' => 'API error: '.$e->getMessage(),
            ]);

            Log::error('Failed to send order to courier', [
                'order_id' => $order->id,
                'courier' => $courierName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Track a shipment via courier API.
     */
    public function trackShipment(Shipment $shipment): array
    {
        if (! $shipment->courier_code || ! $shipment->tracking_number) {
            throw new \RuntimeException('Shipment has no courier or tracking number.');
        }

        $gateway = CourierGatewayFactory::make($shipment->courier_code);

        if (! $gateway) {
            throw new \RuntimeException("Courier '{$shipment->courier}' does not support API tracking.");
        }

        $result = $gateway->trackShipment($shipment->tracking_number);

        $shipment->update([
            'status' => $result['status'],
            'courier_response' => $result['raw'],
        ]);

        return $result;
    }

    /**
     * Test courier API connection.
     */
    public function testConnection(string $courierCode): bool
    {
        $gateway = CourierGatewayFactory::make($courierCode);

        if (! $gateway) {
            return false;
        }

        return $gateway->testConnection();
    }
}
