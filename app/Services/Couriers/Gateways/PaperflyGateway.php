<?php

namespace App\Services\Couriers\Gateways;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaperflyGateway implements CourierGateway
{
    private string $merchantId;

    private string $username;

    private string $password;

    private string $baseUrl;

    public function __construct(array $settings)
    {
        $this->merchantId = $settings['merchant_id'] ?? '';
        $this->username = $settings['username'] ?? '';
        $this->password = $settings['password'] ?? '';
        $this->baseUrl = 'https://api.paperfly.com.bd';
    }

    public function createShipment(Order $order, Shipment $shipment): array
    {
        $payload = [
            'merchant_order_id' => $order->order_number,
            'customer_name' => $order->shipping_name,
            'customer_phone' => $order->shipping_phone,
            'delivery_address' => trim("{$order->shipping_address}, {$order->shipping_city}"),
            'thana' => $order->shipping_state ?? '',
            'district' => $order->shipping_city,
            'cod_amount' => (int) $order->total,
            'product_weight' => 0.5,
            'product_description' => $order->items->pluck('name')->implode(', '),
            'special_note' => '',
        ];

        $response = $this->apiRequest('POST', '/api/v1/orders', $payload);

        $data = $response->json();

        if ($response->failed() || empty($data['tracking_number'])) {
            Log::error('Paperfly create order failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException(
                $data['message'] ?? 'Paperfly API error: '.$response->body()
            );
        }

        return [
            'consignment_id' => $data['tracking_number'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'delivery_fee' => $data['delivery_fee'] ?? 0,
            'raw' => $data,
        ];
    }

    public function trackShipment(string $trackingNumber): array
    {
        $response = $this->apiRequest('GET', "/api/v1/orders/{$trackingNumber}/track");

        if ($response->failed()) {
            throw new \RuntimeException('Paperfly tracking failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'status' => $this->mapStatus($data['status'] ?? 'pending'),
            'raw' => $data,
        ];
    }

    public function cancelShipment(string $courierOrderId): bool
    {
        throw new \RuntimeException('Paperfly does not support cancellation via API');
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->apiRequest('GET', '/api/v1/orders?limit=1');

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Paperfly connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function apiRequest(string $method, string $endpoint, ?array $payload = null): Response
    {
        $url = $this->baseUrl.$endpoint;

        $request = Http::withHeaders([
            'merchant-id' => $this->merchantId,
            'username' => $this->username,
            'password' => $this->password,
        ])->timeout(30)->acceptJson()->asJson();

        return match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    private function mapStatus(string $paperflyStatus): string
    {
        return match (strtolower($paperflyStatus)) {
            'pending' => 'pending',
            'picked_up' => 'picked',
            'in_transit' => 'in_transit',
            'partial_delivered' => 'out_for_delivery',
            'delivered' => 'delivered',
            'cancelled' => 'failed',
            'returned' => 'returned',
            default => 'pending',
        };
    }

    public static function settingsSchema(): array
    {
        return [
            ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text', 'required' => true, 'placeholder' => 'Paperfly merchant ID'],
            ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true, 'placeholder' => 'Merchant username'],
            ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'placeholder' => 'Merchant password'],
        ];
    }
}
