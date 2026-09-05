<?php

namespace App\Services\Couriers;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Log;

class CourierService
{
    /**
     * Send an order to the courier API.
     */
    public function sendToCourier(Order $order, Courier $courier): Shipment
    {
        $gateway = CourierGatewayFactory::make($courier);

        if (! $gateway) {
            throw new \RuntimeException("Courier '{$courier->name}' is not configured or does not support API integration.");
        }

        $shipment = $order->shipments()->create([
            'courier_id' => $courier->id,
            'courier' => $courier->name,
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

            $order->update([
                'fulfillment_type' => 'courier',
                'shipped_at' => now(),
                'status' => 'shipped',
            ]);

            Log::info('Order sent to courier', [
                'order_id' => $order->id,
                'courier' => $courier->name,
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
                'courier' => $courier->name,
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
        if (! $shipment->courier_id || ! $shipment->tracking_number) {
            throw new \RuntimeException('Shipment has no courier or tracking number.');
        }

        $courier = $shipment->courierRelation;
        $gateway = CourierGatewayFactory::make($courier);

        if (! $gateway) {
            throw new \RuntimeException("Courier '{$courier->name}' does not support API tracking.");
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
    public function testConnection(Courier $courier): bool
    {
        $gateway = CourierGatewayFactory::make($courier);

        if (! $gateway) {
            return false;
        }

        return $gateway->testConnection();
    }
}
