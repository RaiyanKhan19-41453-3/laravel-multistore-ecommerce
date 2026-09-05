<?php

namespace App\Services\PaymentGateways;

class PaymentGatewayFactory
{
    private static array $gatewayMap = [
        'sslcommerz' => SSLCommerzGateway::class,
        'bkash' => BkashGateway::class,
    ];

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
            if ($isActive) {
                $methods[] = $name;
            }
        }

        return $methods;
    }

    public static function isEnabled(string $gatewayName): bool
    {
        return config("payment.enabled.{$gatewayName}", false);
    }
}
