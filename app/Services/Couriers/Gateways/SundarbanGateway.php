<?php

namespace App\Services\Couriers\Gateways;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SundarbanGateway implements CourierGateway
{
    private string $apiKey;

    private string $bookingUserId;

    private string $baseUrl;

    public function __construct(array $settings)
    {
        $this->apiKey = $settings['api_key'] ?? '';
        $this->bookingUserId = $settings['booking_user_id'] ?? '';
        $this->baseUrl = 'https://api.sundarbancourier.com';
    }

    public function createShipment(Order $order, Shipment $shipment): array
    {
        $payload = [
            'booking_user_id' => $this->bookingUserId,
            'reference_no' => $order->order_number,
            'consignee_name' => $order->shipping_name,
            'consignee_mobile' => $order->shipping_phone,
            'consignee_address' => trim("{$order->shipping_address}, {$order->shipping_city}"),
            'destination_thana' => $order->shipping_state ?? '',
            'destination_district' => $order->shipping_city,
            'cod_amount' => (int) $order->total,
            'weight' => 0.5,
            'goods_description' => $order->items->pluck('name')->implode(', '),
            'remarks' => '',
        ];

        $response = $this->apiRequest('POST', '/api/booking', $payload);

        $data = $response->json();

        if ($response->failed() || empty($data['consignment_no'])) {
            Log::error('Sundarban create booking failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException(
                $data['message'] ?? 'Sundarban API error: '.$response->body()
            );
        }

        return [
            'consignment_id' => $data['consignment_no'] ?? null,
            'tracking_number' => $data['consignment_no'] ?? null,
            'delivery_fee' => $data['delivery_fee'] ?? 0,
            'raw' => $data,
        ];
    }

    public function trackShipment(string $trackingNumber): array
    {
        $response = $this->apiRequest('GET', "/api/track/{$trackingNumber}");

        $data = $response->json();

        if ($response->failed()) {
            throw new \RuntimeException('Sundarban tracking failed: '.($data['message'] ?? $response->body()));
        }

        return [
            'status' => $this->mapStatus($data['current_status'] ?? 'pending'),
            'raw' => $data,
        ];
    }

    public function cancelShipment(string $courierOrderId): bool
    {
        throw new \RuntimeException('Sundarban does not support cancellation via API');
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->apiRequest('GET', '/api/track/test');

            return $response->successful() || $response->status() === 404;
        } catch (\Exception $e) {
            Log::warning('Sundarban connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function apiRequest(string $method, string $endpoint, ?array $payload = null): Response
    {
        $url = $this->baseUrl.$endpoint;

        $request = Http::withToken($this->apiKey)
            ->timeout(30)
            ->acceptJson()
            ->asJson();

        return match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    private function mapStatus(string $sundarbanStatus): string
    {
        return match (strtolower($sundarbanStatus)) {
            'booked', 'pending' => 'pending',
            'picked up' => 'picked',
            'in transit', 'at hub' => 'in_transit',
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
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'Sundarban API token'],
            ['key' => 'booking_user_id', 'label' => 'Booking User ID', 'type' => 'text', 'required' => true, 'placeholder' => 'Sundarban booking user ID'],
        ];
    }
}
