<?php

namespace App\Services\Couriers\Gateways;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SAParibahanGateway implements CourierGateway
{
    private string $apiKey;

    private string $bookingBranch;

    private string $baseUrl;

    public function __construct(array $settings)
    {
        $this->apiKey = $settings['api_key'] ?? '';
        $this->bookingBranch = $settings['booking_branch'] ?? '';
        $this->baseUrl = 'https://api.saparibahan.com';
    }

    public function createShipment(Order $order, Shipment $shipment): array
    {
        $payload = [
            'booking_branch' => $this->bookingBranch,
            'reference_no' => $order->order_number,
            'receiver_name' => $order->shipping_name,
            'receiver_mobile' => $order->shipping_phone,
            'receiver_address' => trim("{$order->shipping_address}, {$order->shipping_city}"),
            'destination_branch' => $order->shipping_city,
            'cod_amount' => (int) $order->total,
            'weight' => 0.5,
            'description' => $order->items->pluck('name')->implode(', '),
            'remarks' => '',
        ];

        $response = $this->apiRequest('POST', '/api/parcel/booking', $payload);

        $data = $response->json();

        if ($response->failed() || empty($data['tracking_no'])) {
            Log::error('SA Paribahan create booking failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException(
                $data['message'] ?? 'SA Paribahan API error: '.$response->body()
            );
        }

        return [
            'consignment_id' => $data['tracking_no'] ?? null,
            'tracking_number' => $data['tracking_no'] ?? null,
            'delivery_fee' => $data['delivery_fee'] ?? 0,
            'raw' => $data,
        ];
    }

    public function trackShipment(string $trackingNumber): array
    {
        $response = $this->apiRequest('GET', "/api/parcel/track/{$trackingNumber}");

        $data = $response->json();

        if ($response->failed()) {
            throw new \RuntimeException('SA Paribahan tracking failed: '.($data['message'] ?? $response->body()));
        }

        return [
            'status' => $this->mapStatus($data['status'] ?? 'pending'),
            'raw' => $data,
        ];
    }

    public function cancelShipment(string $courierOrderId): bool
    {
        throw new \RuntimeException('SA Paribahan does not support cancellation via API');
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->apiRequest('GET', '/api/parcel/track/test');

            return $response->successful() || $response->status() === 404;
        } catch (\Exception $e) {
            Log::warning('SA Paribahan connection test failed', ['error' => $e->getMessage()]);

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

    private function mapStatus(string $saStatus): string
    {
        return match (strtolower($saStatus)) {
            'booked', 'pending' => 'pending',
            'picked up' => 'picked',
            'in transit' => 'in_transit',
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
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'SA Paribahan API token'],
            ['key' => 'booking_branch', 'label' => 'Booking Branch', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Dhaka'],
        ];
    }
}
