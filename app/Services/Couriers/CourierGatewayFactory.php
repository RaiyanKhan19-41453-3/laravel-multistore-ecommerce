<?php

namespace App\Services\Couriers;

use App\Models\Courier;
use App\Services\Couriers\Gateways\AramexGateway;
use App\Services\Couriers\Gateways\CourierGateway;
use App\Services\Couriers\Gateways\ECourierGateway;
use App\Services\Couriers\Gateways\PaperflyGateway;
use App\Services\Couriers\Gateways\PathaoGateway;
use App\Services\Couriers\Gateways\RedXGateway;
use App\Services\Couriers\Gateways\SAParibahanGateway;
use App\Services\Couriers\Gateways\SmsaGateway;
use App\Services\Couriers\Gateways\SteadfastGateway;
use App\Services\Couriers\Gateways\SundarbanGateway;

class CourierGatewayFactory
{
    private static array $gatewayMap = [
        'pathao' => PathaoGateway::class,
        'redx' => RedXGateway::class,
        'paperfly' => PaperflyGateway::class,
        'steadfast' => SteadfastGateway::class,
        'ecourier' => ECourierGateway::class,
        'sa_paribahan' => SAParibahanGateway::class,
        'sundarban' => SundarbanGateway::class,
        'smsa' => SmsaGateway::class,
        'aramex' => AramexGateway::class,
    ];

    public static function make(Courier $courier): ?CourierGateway
    {
        $code = strtolower($courier->code);

        $class = self::$gatewayMap[$code] ?? null;

        if (! $class) {
            return null;
        }

        $settings = $courier->settings ?? [];

        if (empty($settings)) {
            return null;
        }

        return new $class($settings);
    }

    public static function supportsApi(string $courierCode): bool
    {
        return isset(self::$gatewayMap[strtolower($courierCode)]);
    }

    /**
     * @return array<array{key: string, label: string, type: string, required: bool, placeholder?: string}>
     */
    public static function getSettingsSchema(string $courierCode): array
    {
        $class = self::$gatewayMap[strtolower($courierCode)] ?? null;

        if (! $class || ! method_exists($class, 'settingsSchema')) {
            return [];
        }

        return $class::settingsSchema();
    }
}
