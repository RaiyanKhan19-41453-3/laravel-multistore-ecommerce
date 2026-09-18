<?php

namespace App\Services\Couriers\Gateways;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteadfastGateway implements CourierGateway
{
    private string $apiKey;

    private string $secretKey;

    private string $baseUrl;

    public function __construct(array $settings)
    {
        $this->apiKey = $settings['api_key'] ?? '';
        $this->secretKey = $settings['secret_key'] ?? '';
        $this->baseUrl = 'https://portal.packzy.com/api/v1';
    }

    public function createShipment(Order $order, Shipment $shipment): array
    {
        $payload = [
            'invoice' => $order->order_number,
            'recipient_name' => $order->shipping_name,
            'recipient_phone' => $order->shipping_phone,
            'recipient_address' => trim("{$order->shipping_address}, {$order->shipping_city}"),
            'cod_amount' => (int) $order->total,
            'note' => '',
            'item_description' => $order->items->pluck('name')->implode(', '),
            'total_lot' => $order->items->sum('quantity'),
            'delivery_type' => 0,
        ];

        $response = $this->apiRequest('POST', '/create_order', $payload);

        $data = $response->json();

        if ($response->failed() || ($data['status'] ?? 0) !== 200) {
            Log::error('Steadfast create order failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException(
                $data['message'] ?? 'Steadfast API error: '.$response->body()
            );
        }

        $consignment = $data['consignment'] ?? [];

        return [
            'consignment_id' => $consignment['consignment_id'] ?? null,
            'tracking_number' => $consignment['tracking_code'] ?? null,
            'delivery_fee' => 0,
            'raw' => $data,
        ];
    }

    public function trackShipment(string $trackingNumber): array
    {
        $response = $this->apiRequest('GET', "/status_by_invoice/{$trackingNumber}");

        $data = $response->json();

        if ($response->failed() || ($data['status'] ?? 0) !== 200) {
            throw new \RuntimeException('Steadfast tracking failed: '.($data['message'] ?? $response->body()));
        }

        return [
            'status' => $this->mapStatus($data['delivery_status'] ?? 'pending'),
            'raw' => $data,
        ];
    }

    public function cancelShipment(string $courierOrderId): bool
    {
        throw new \RuntimeException('Steadfast does not support cancellation via API');
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->apiRequest('GET', '/get_balance');

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Steadfast connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function apiRequest(string $method, string $endpoint, ?array $payload = null): Response
    {
        $url = $this->baseUrl.$endpoint;

        $request = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->acceptJson();

        return match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    private function mapStatus(string $steadfastStatus): string
    {
        return match (strtolower($steadfastStatus)) {
            'pending', 'in_review', 'unknown_approval_pending' => 'pending',
            'delivered_approval_pending', 'partial_delivered_approval_pending' => 'out_for_delivery',
            'delivered' => 'delivered',
            'cancelled_approval_pending' => 'failed',
            'hold' => 'pending',
            default => 'pending',
        };
    }

    public static function settingsSchema(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'required' => true, 'placeholder' => 'Steadfast API key'],
            ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true, 'placeholder' => 'Steadfast secret key'],
        ];
    }
}
