<?php

namespace App\Services\Couriers\Gateways;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RedXGateway implements CourierGateway
{
    private string $apiToken;

    private string $baseUrl;

    public function __construct(array $settings)
    {
        $this->apiToken = $settings['api_token'] ?? '';
        $this->baseUrl = ($settings['sandbox'] ?? true)
            ? 'https://sandbox.redx.com.bd/v1.0.0-beta'
            : 'https://openapi.redx.com.bd/v1.0.0-beta';
    }

    public function createShipment(Order $order, Shipment $shipment): array
    {
        $payload = [
            'customer_name' => $order->shipping_name,
            'customer_phone' => $order->shipping_phone,
            'delivery_area' => $order->shipping_city,
            'delivery_area_id' => (int) ($order->shipping_state ?? 0),
            'customer_address' => $order->shipping_address,
            'merchant_invoice_id' => $order->order_number,
            'cash_collection_amount' => (string) $order->total,
            'parcel_weight' => 500,
            'value' => (int) $order->total,
        ];

        $response = $this->apiRequest('POST', '/parcel', $payload);

        if ($response->failed() || empty($response->json()['tracking_id'])) {
            Log::error('RedX create parcel failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new \RuntimeException(
                $response->json()['message'] ?? 'RedX API error: '.$response->body()
            );
        }

        $data = $response->json();

        return [
            'consignment_id' => $data['tracking_id'] ?? null,
            'tracking_number' => $data['tracking_id'] ?? null,
            'delivery_fee' => $data['cash_on_delivery'] ?? 0,
            'raw' => $data,
        ];
    }

    public function trackShipment(string $trackingNumber): array
    {
        $response = $this->apiRequest('GET', "/parcel/track/{$trackingNumber}");

        if ($response->failed()) {
            throw new \RuntimeException('RedX tracking failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'status' => $this->mapStatus($data['current_status'] ?? 'pending'),
            'raw' => $data,
        ];
    }

    public function cancelShipment(string $courierOrderId): bool
    {
        $response = $this->apiRequest('PATCH', '/parcels', [
            'entity_type' => 'parcel-tracking-id',
            'entity_id' => $courierOrderId,
            'update_details' => [
                'reason' => 'Cancelled by merchant',
            ],
        ]);

        return $response->successful();
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->apiRequest('GET', '/areas');

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('RedX connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function apiRequest(string $method, string $endpoint, ?array $payload = null): Response
    {
        $url = $this->baseUrl.$endpoint;

        $request = Http::withHeaders([
            'API-ACCESS-TOKEN' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])->timeout(30);

        return match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $payload),
            'PATCH' => $request->patch($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    private function mapStatus(string $redxStatus): string
    {
        return match (strtolower($redxStatus)) {
            'pending' => 'pending',
            'hold' => 'pending',
            'in-transit', 'in_transit' => 'in_transit',
            'out-for-delivery', 'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'cancelled' => 'failed',
            'returned', 'return' => 'returned',
            default => 'pending',
        };
    }

    public static function settingsSchema(): array
    {
        return [
            ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true, 'placeholder' => 'RedX JWT token'],
        ];
    }
}
