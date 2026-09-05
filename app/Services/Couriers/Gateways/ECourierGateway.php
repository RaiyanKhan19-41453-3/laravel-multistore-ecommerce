<?php

namespace App\Services\Couriers\Gateways;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ECourierGateway implements CourierGateway
{
    private string $userId;

    private string $apiKey;

    private string $baseUrl;

    public function __construct(array $settings)
    {
        $this->userId = $settings['user_id'] ?? '';
        $this->apiKey = $settings['api_key'] ?? '';
        $this->baseUrl = 'https://backoffice.ecourier.com.bd';
    }

    public function createShipment(Order $order, Shipment $shipment): array
    {
        $payload = [
            'invoice' => $order->order_number,
            'customer_name' => $order->shipping_name,
            'mobile' => $order->shipping_phone,
            'address' => trim("{$order->shipping_address}, {$order->shipping_city}"),
            'thana' => $order->shipping_state ?? '',
            'district' => $order->shipping_city,
            'cod_amount' => (int) $order->total,
            'product_details' => $order->items->pluck('name')->implode(', '),
            'weight' => 0.5,
            'special_instruction' => '',
        ];

        $response = $this->apiRequest('POST', '/rest-api/courier-orders', $payload);

        $data = $response->json();

        if ($response->failed() || empty($data['data']['order_id'])) {
            Log::error('eCourier create order failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException(
                $data['message'] ?? 'eCourier API error: '.$response->body()
            );
        }

        return [
            'consignment_id' => $data['data']['order_id'] ?? null,
            'tracking_number' => $data['data']['order_id'] ?? null,
            'delivery_fee' => $data['data']['delivery_fee'] ?? 0,
            'raw' => $data,
        ];
    }

    public function trackShipment(string $trackingNumber): array
    {
        $response = $this->apiRequest('GET', "/rest-api/courier-orders/{$trackingNumber}");

        $data = $response->json();

        if ($response->failed()) {
            throw new \RuntimeException('eCourier tracking failed: '.($data['message'] ?? $response->body()));
        }

        $orderData = $data['data'] ?? [];

        return [
            'status' => $this->mapStatus($orderData['status'] ?? 'pending'),
            'raw' => $data,
        ];
    }

    public function cancelShipment(string $courierOrderId): bool
    {
        throw new \RuntimeException('eCourier does not support cancellation via API');
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->apiRequest('GET', '/rest-api/courier-orders?limit=1');

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('eCourier connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function apiRequest(string $method, string $endpoint, ?array $payload = null): Response
    {
        $url = $this->baseUrl.$endpoint;

        $request = Http::withHeaders([
            'userid' => $this->userId,
            'apikey' => $this->apiKey,
        ])->timeout(30)->acceptJson()->asJson();

        return match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    private function mapStatus(string $ecourierStatus): string
    {
        return match (strtolower($ecourierStatus)) {
            'pending' => 'pending',
            'picked', 'picked up' => 'picked',
            'in transit', 'processing' => 'in_transit',
            'partial delivered' => 'out_for_delivery',
            'delivered' => 'delivered',
            'cancelled' => 'failed',
            'returned' => 'returned',
            default => 'pending',
        };
    }

    public static function settingsSchema(): array
    {
        return [
            ['key' => 'user_id', 'label' => 'User ID', 'type' => 'text', 'required' => true, 'placeholder' => 'eCourier user ID'],
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'eCourier API key'],
        ];
    }
}
