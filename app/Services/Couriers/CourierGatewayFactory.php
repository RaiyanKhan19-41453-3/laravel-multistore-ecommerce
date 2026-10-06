<?php

namespace App\Services\Couriers;

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

    /**
     * Courier catalog for admin display, in configured order.
     *
     * @return array<int, array{code: string, name: string, enabled: bool, configured: bool, supports_api: bool}>
     */
    public static function catalog(): array
    {
        $couriers = config('couriers', []);
        $rows = [];

        foreach ($couriers as $code => $courier) {
            $rows[] = [
                'code' => $code,
                'name' => $courier['name'] ?? $code,
                'enabled' => (bool) ($courier['enabled'] ?? false),
                'configured' => self::isConfigured((string) $code),
                'supports_api' => self::supportsApi((string) $code),
            ];
        }

        return $rows;
    }

    public static function isEnabled(string $courierCode): bool
    {
        return (bool) config('couriers.'.strtolower($courierCode).'.enabled', false);
    }

    /**
     * All required credential fields present (empty values do not count).
     */
    public static function isConfigured(string $courierCode): bool
    {
        $code = strtolower($courierCode);

        if (! isset(self::$gatewayMap[$code])) {
            return $code === 'other';
        }

        $settings = config("couriers.{$code}.settings", []) ?? [];

        foreach (self::getSettingsSchema($code) as $field) {
            if (($field['required'] ?? false) && empty($settings[$field['key']])) {
                return false;
            }
        }

        return true;
    }

    public static function settingsFor(string $courierCode): array
    {
        $settings = config('couriers.'.strtolower($courierCode).'.settings', []) ?? [];

        return array_filter($settings, fn ($value) => $value !== null && $value !== '');
    }

    public static function make(string $courierCode): ?CourierGateway
    {
        $code = strtolower($courierCode);

        $class = self::$gatewayMap[$code] ?? null;

        if (! $class) {
            return null;
        }

        if (! self::isEnabled($code) || ! self::isConfigured($code)) {
            return null;
        }

        return new $class(self::settingsFor($code));
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
