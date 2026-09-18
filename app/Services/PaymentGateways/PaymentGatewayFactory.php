<?php

namespace App\Services\PaymentGateways;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Schema;

class PaymentGatewayFactory
{
    private static array $gatewayMap = [
        'sslcommerz' => SSLCommerzGateway::class,
        'bkash' => BkashGateway::class,
        'moyasar' => MoyasarGateway::class,
        'tabby' => TabbyGateway::class,
        'stripe' => StripeGateway::class,
    ];

    private static function settingsOverride(string $gatewayName): ?bool
    {
        try {
            if (! Schema::hasTable('settings')) {
                return null;
            }

            /** @var SettingsService $settings */
            $settings = app(SettingsService::class);
            $value = $settings->get("payment.{$gatewayName}_enabled");

            if ($value === null) {
                return null;
            }

            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value === '1';
        } catch (\Throwable) {
            return null;
        }
    }

    public static function make(string $gatewayName): ?PaymentGateway
    {
        $class = self::$gatewayMap[$gatewayName] ?? null;

        if (! $class) {
            return null;
        }

        return new $class;
    }

    public static function getEnabled(): array
    {
        $enabled = config('payment.enabled', []);
        $methods = [];

        foreach ($enabled as $name => $isActive) {
            // Skip flags for gateways with no implementation (e.g. nagad,
            // rocket): offering them breaks checkout after order creation.
            if ($name !== 'cod' && ! isset(self::$gatewayMap[$name])) {
                continue;
            }

            $override = self::settingsOverride($name);

            $active = $override !== null ? $override : (bool) $isActive;

            if ($active) {
                $methods[] = $name;
            }
        }

        // Include any gateway enabled via settings but missing from config
        foreach (array_keys(self::$gatewayMap) as $name) {
            if (! array_key_exists($name, $enabled)) {
                if (self::settingsOverride($name) === true) {
                    $methods[] = $name;
                }
            }
        }

        if (! array_key_exists('cod', $enabled)) {
            $override = self::settingsOverride('cod');

            $active = $override !== null ? $override : true;

            if ($active && ! in_array('cod', $methods, true)) {
                array_unshift($methods, 'cod');
            }
        }

        return $methods;
    }

    public static function isEnabled(string $gatewayName): bool
    {
        $override = self::settingsOverride($gatewayName);

        if ($override !== null) {
            return $override;
        }

        return (bool) config("payment.enabled.{$gatewayName}", false);
    }
}
