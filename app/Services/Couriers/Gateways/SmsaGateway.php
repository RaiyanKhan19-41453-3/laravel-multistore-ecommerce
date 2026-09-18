<?php

namespace App\Services\Couriers\Gateways;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsaGateway implements CourierGateway
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct(array $settings)
    {
        $this->apiKey = $settings['api_key'] ?? $settings['apiKey'] ?? '';
        $this->baseUrl = rtrim($settings['base_url'] ?? ($settings['sandbox'] ?? false ? 'https://ecom-uat.smsaexpress.com' : 'https://ecom.smsaexpress.com'), '/');
    }

    public function createShipment(Order $order, Shipment $shipment): array
    {
        $payload = [
            'orderNumber' => $order->order_number,
            'shipperName' => config('store.name', config('app.name')),
            'consigneeName' => $order->shipping_name,
            'consigneePhone' => $order->shipping_phone,
            'consigneeAddress' => trim("{$order->shipping_address}, {$order->shipping_city}, {$order->shipping_country}"),
            'consigneeCity' => $order->shipping_city,
            'consigneeCountry' => $order->shipping_country ?? 'SA',
            'codAmount' => $order->payment?->gateway === 'cod' || $order->payments->where('method', 'cod')->isNotEmpty() ? (float) $order->total : 0,
            'weight' => 1,
            'pieces' => $order->items->sum('quantity'),
            'description' => $order->items->pluck('name')->implode(', '),
            'reference' => $order->order_number,
        ];

        $response = Http::withHeaders([
            'apikey' => $this->apiKey,
            'Accept' => 'application/json',
        ])->timeout(30)->post($this->baseUrl.'/api/shipment/b2c', $payload);

        $data = $response->json();

        if ($response->failed() || empty($data['awb'] ?? $data['awbNumber'] ?? $data['trackingNumber'] ?? null)) {
            Log::error('SMSA create shipment failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException($data['message'] ?? $data['error'] ?? 'SMSA API error: '.$response->body());
        }

        $tracking = $data['awb'] ?? $data['awbNumber'] ?? $data['trackingNumber'];
        $consignmentId = $data['consignmentId'] ?? $tracking;

        return [
            'consignment_id' => (string) $consignmentId,
            'tracking_number' => (string) $tracking,
            'delivery_fee' => (float) ($data['codAmount'] ?? $data['price'] ?? 0),
            'raw' => $data,
        ];
    }

    public function trackShipment(string $trackingNumber): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->apiKey,
        ])->timeout(15)->get($this->baseUrl."/api/track/{$trackingNumber}");

        $data = $response->json();

        if ($response->failed()) {
            throw new \RuntimeException('SMSA tracking failed: '.($data['message'] ?? $response->body()));
        }

        $statusRaw = $data['status'] ?? $data['currentStatus'] ?? $data['trackingInfo'][0]['status'] ?? 'pending';

        return [
            'status' => $this->mapStatus((string) $statusRaw),
            'raw' => $data,
        ];
    }

    public function cancelShipment(string $courierOrderId): bool
    {
        $response = Http::withHeaders([
            'apikey' => $this->apiKey,
        ])->timeout(15)->post($this->baseUrl."/api/shipment/cancel/{$courierOrderId}");

        $data = $response->json();

        return $response->successful() && (($data['success'] ?? false) || ($data['status'] ?? '') === 'cancelled');
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->timeout(15)->get($this->baseUrl.'/api/track/test');

            // Any 2xx or 404 with auth error means connectivity; only network errors fail
            return $response->status() !== 0;
        } catch (\Exception $e) {
            Log::warning('SMSA connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending', 'created', 'booked' => 'pending',
            'picked', 'picked up', 'received' => 'picked',
            'in transit', 'in_transit', 'out for delivery', 'out_for_delivery' => 'in_transit',
            'delivered' => 'delivered',
            'cancelled', 'canceled', 'failed' => 'failed',
            'returned', 'return to origin', 'rto' => 'returned',
            default => 'pending',
        };
    }

    public static function settingsSchema(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'SMSA API key'],
            ['key' => 'base_url', 'label' => 'Base URL (optional)', 'type' => 'text', 'required' => false, 'placeholder' => 'https://ecom.smsaexpress.com'],
        ];
    }
}
