<?php

namespace App\Services\Couriers\Gateways;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PathaoGateway implements CourierGateway
{
    private string $clientId;

    private string $clientSecret;

    private string $username;

    private string $password;

    private string $storeId;

    private string $baseUrl;

    private ?string $cachedToken = null;

    public function __construct(array $settings)
    {
        $this->clientId = $settings['client_id'] ?? '';
        $this->clientSecret = $settings['client_secret'] ?? '';
        $this->username = $settings['username'] ?? '';
        $this->password = $settings['password'] ?? '';
        $this->storeId = $settings['store_id'] ?? '';
        $this->baseUrl = ($settings['sandbox'] ?? true)
            ? 'https://courier-sandbox.pathao.com'
            : 'https://api-hermes.pathao.com';
    }

    public function createShipment(Order $order, Shipment $shipment): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'store_id' => (int) $this->storeId,
            'merchant_order_id' => $order->order_number,
            'recipient_name' => $order->shipping_name,
            'recipient_phone' => $order->shipping_phone,
            'recipient_address' => trim("{$order->shipping_address}, {$order->shipping_city}"),
            'delivery_type' => 48,
            'item_type' => 2,
            'item_quantity' => $order->items->sum('quantity'),
            'item_weight' => 0.5,
            'amount_to_collect' => (int) round((float) $order->total),
            'item_description' => $order->items->pluck('name')->implode(', '),
        ];

        $response = Http::withToken($token)
            ->timeout(30)
            ->post("{$this->baseUrl}/aladdin/api/v1/orders", $payload);

        $data = $response->json();

        if ($response->failed() || ! empty($data['error'])) {
            Log::error('Pathao create shipment failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException(
                $data['error_description'] ?? $data['message'] ?? 'Pathao API error: '.$response->body()
            );
        }

        return [
            'consignment_id' => $data['data']['consignment_id'] ?? null,
            'tracking_number' => $data['data']['invoice_id'] ?? $data['data']['consignment_id'] ?? null,
            'delivery_fee' => $data['data']['delivery_fee'] ?? 0,
            'raw' => $data,
        ];
    }

    public function trackShipment(string $trackingNumber): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(15)
            ->get("{$this->baseUrl}/aladdin/api/v1/orders/{$trackingNumber}/info");

        $data = $response->json();

        if ($response->failed() || ! empty($data['error'])) {
            throw new \RuntimeException('Pathao tracking failed: '.($data['error_description'] ?? $response->body()));
        }

        return [
            'status' => $this->mapStatus($data['data']['order_status'] ?? 'pending'),
            'raw' => $data,
        ];
    }

    public function cancelShipment(string $courierOrderId): bool
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(15)
            ->post("{$this->baseUrl}/aladdin/api/v1/orders/{$courierOrderId}/cancel");

        $data = $response->json();

        return $response->successful() && empty($data['error']);
    }

    public function testConnection(): bool
    {
        try {
            $this->getAccessToken();

            return true;
        } catch (\Exception $e) {
            Log::warning('Pathao connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function getAccessToken(): string
    {
        if ($this->cachedToken) {
            return $this->cachedToken;
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post("{$this->baseUrl}/aladdin/api/v1/issue-token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'username' => $this->username,
                'password' => $this->password,
            ]);

        $data = $response->json();

        if ($response->failed() || empty($data['data']['access_token'])) {
            throw new \RuntimeException(
                'Pathao authentication failed: '.($data['error_description'] ?? $response->body())
            );
        }

        $this->cachedToken = $data['data']['access_token'];

        return $this->cachedToken;
    }

    private function mapStatus(string $pathaoStatus): string
    {
        return match (strtolower($pathaoStatus)) {
            'pending' => 'pending',
            'picked' => 'picked',
            'in-transit', 'in_transit' => 'in_transit',
            'delivered' => 'delivered',
            'cancelled', 'return-to-delivery-hub' => 'failed',
            'returned' => 'returned',
            default => 'pending',
        };
    }

    public static function settingsSchema(): array
    {
        return [
            ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true, 'placeholder' => 'Pathao client ID'],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true, 'placeholder' => 'Pathao client secret'],
            ['key' => 'username', 'label' => 'Username (merchant email)', 'type' => 'text', 'required' => true, 'placeholder' => 'merchant@example.com'],
            ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'placeholder' => 'Merchant password'],
            ['key' => 'store_id', 'label' => 'Store ID', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 149043'],
        ];
    }
}
