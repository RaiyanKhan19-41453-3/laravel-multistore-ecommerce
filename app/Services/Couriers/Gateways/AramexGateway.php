<?php

namespace App\Services\Couriers\Gateways;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AramexGateway implements CourierGateway
{
    private string $accountNumber;

    private string $username;

    private string $password;

    private string $accountPin;

    private string $baseUrl;

    public function __construct(array $settings)
    {
        $this->accountNumber = $settings['account_number'] ?? $settings['accountNumber'] ?? '';
        $this->username = $settings['username'] ?? '';
        $this->password = $settings['password'] ?? '';
        $this->accountPin = $settings['account_pin'] ?? $settings['accountPin'] ?? '';
        $this->baseUrl = rtrim($settings['base_url'] ?? 'https://ws.aramex.net', '/');
    }

    public function createShipment(Order $order, Shipment $shipment): array
    {
        $payload = [
            'ClientInfo' => [
                'UserName' => $this->username,
                'Password' => $this->password,
                'Version' => 'v1',
                'AccountNumber' => $this->accountNumber,
                'AccountPin' => $this->accountPin,
                'AccountEntity' => 'RUH',
                'AccountCountryCode' => 'SA',
            ],
            'Shipments' => [[
                'Reference1' => $order->order_number,
                'Reference2' => (string) $order->id,
                'Shipper' => [
                    'PartyAddress' => [
                        'City' => config('store.name', 'Riyadh'),
                        'CountryCode' => 'SA',
                    ],
                    'Contact' => [
                        'PersonName' => config('store.name', config('app.name')),
                        'PhoneNumber1' => '0000000000',
                    ],
                ],
                'Consignee' => [
                    'PartyAddress' => [
                        'Line1' => $order->shipping_address,
                        'City' => $order->shipping_city,
                        'CountryCode' => $order->shipping_country ?? 'SA',
                        'PostCode' => $order->shipping_postal_code ?? '',
                    ],
                    'Contact' => [
                        'PersonName' => $order->shipping_name,
                        'PhoneNumber1' => $order->shipping_phone,
                    ],
                ],
                'ShippingDateTime' => now()->toIso8601String(),
                'DueDate' => now()->addDays(3)->toIso8601String(),
                'Details' => [
                    'ActualWeight' => ['Value' => 1, 'Unit' => 'KG'],
                    'NumberOfPieces' => $order->items->sum('quantity'),
                    'ProductGroup' => 'EXP',
                    'ProductType' => 'PPX',
                    'DescriptionOfGoods' => $order->items->pluck('name')->implode(', '),
                    'CashOnDeliveryAmount' => ['Value' => (float) $order->total, 'CurrencyCode' => $order->shipping_country === 'BD' ? 'BDT' : 'SAR'],
                ],
            ]],
            'LabelInfo' => ['ReportID' => 9729, 'ReportType' => 'URL'],
        ];

        $response = Http::timeout(30)
            ->acceptJson()
            ->post($this->baseUrl.'/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CreateShipments', $payload);

        $data = $response->json();

        if ($response->failed() || ($data['HasErrors'] ?? false)) {
            $notifications = $data['Notifications'][0]['Message'] ?? $data['error'] ?? null;

            Log::error('Aramex create shipment failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException($notifications ?? 'Aramex API error: '.$response->body());
        }

        $shipmentInfo = $data['Shipments'][0] ?? $data['shipments'][0] ?? [];

        $tracking = $shipmentInfo['ID'] ?? $shipmentInfo['AWBNumber'] ?? $shipmentInfo['id'] ?? null;

        if (! $tracking) {
            throw new \RuntimeException('Aramex did not return tracking number');
        }

        return [
            'consignment_id' => (string) $tracking,
            'tracking_number' => (string) $tracking,
            'delivery_fee' => (float) ($shipmentInfo['DeliveryFee'] ?? 0),
            'raw' => $data,
        ];
    }

    public function trackShipment(string $trackingNumber): array
    {
        $payload = [
            'ClientInfo' => [
                'UserName' => $this->username,
                'Password' => $this->password,
                'Version' => 'v1',
                'AccountNumber' => $this->accountNumber,
                'AccountPin' => $this->accountPin,
            ],
            'Shipments' => [$trackingNumber],
            'GetLastTrackingUpdateOnly' => false,
        ];

        $response = Http::timeout(15)
            ->acceptJson()
            ->post($this->baseUrl.'/ShippingAPI.V2/Tracking/Service_1_0.svc/json/TrackShipments', $payload);

        $data = $response->json();

        if ($response->failed() || ($data['HasErrors'] ?? false)) {
            throw new \RuntimeException('Aramex tracking failed: '.($data['Notifications'][0]['Message'] ?? $response->body()));
        }

        $trackingResult = $data['TrackingResults'][0] ?? $data['trackingResults'][0] ?? [];
        $lastUpdate = $trackingResult['Value'][0]['UpdateDescription'] ?? $trackingResult['value'][0]['updateDescription'] ?? 'pending';

        return [
            'status' => $this->mapStatus((string) $lastUpdate),
            'raw' => $data,
        ];
    }

    public function cancelShipment(string $courierOrderId): bool
    {
        throw new \RuntimeException('Aramex does not support cancellation via API');
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::timeout(15)->get($this->baseUrl.'/ShippingAPI.V2/Shipping/Service_1_0.svc/json/'.'GetAllowedCodCities');

            return $response->status() !== 0;
        } catch (\Exception $e) {
            Log::warning('Aramex connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending', 'shipment created', 'created' => 'pending',
            'picked up', 'picked' => 'picked',
            'in transit', 'in_transit', 'out for delivery' => 'in_transit',
            'delivered' => 'delivered',
            'cancelled', 'canceled', 'failed' => 'failed',
            'returned' => 'returned',
            default => 'pending',
        };
    }

    public static function settingsSchema(): array
    {
        return [
            ['key' => 'account_number', 'label' => 'Account Number', 'type' => 'text', 'required' => true, 'placeholder' => 'Aramex account number'],
            ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true, 'placeholder' => 'Aramex username / email'],
            ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'placeholder' => 'Aramex password'],
            ['key' => 'account_pin', 'label' => 'Account PIN', 'type' => 'password', 'required' => true, 'placeholder' => 'Aramex account PIN'],
            ['key' => 'base_url', 'label' => 'Base URL (optional)', 'type' => 'text', 'required' => false, 'placeholder' => 'https://ws.aramex.net'],
        ];
    }
}
