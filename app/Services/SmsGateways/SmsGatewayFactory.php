<?php

namespace App\Services\SmsGateways;

use InvalidArgumentException;

class SmsGatewayFactory
{
    private static array $gateways = [
        'twilio' => TwilioGateway::class,
        'ssl_wireless' => SslWirelessGateway::class,
    ];

    public static function make(string $name): SmsGateway
    {
        $name = strtolower($name);

        if (! isset(self::$gateways[$name])) {
            throw new InvalidArgumentException("SMS gateway [{$name}] is not supported.");
        }

        $gateway = new self::$gateways[$name];

        if (! $gateway->isConfigured()) {
            throw new InvalidArgumentException("SMS gateway [{$name}] is not configured.");
        }

        return $gateway;
    }

    public static function getEnabled(): array
    {
        $enabled = [];

        foreach (config('sms.enabled', []) as $name => $isActive) {
            if (! $isActive) {
                continue;
            }

            $class = self::$gateways[$name] ?? null;

            if ($class && (new $class)->isConfigured()) {
                $enabled[] = $name;
            }
        }

        return $enabled;
    }

    public static function isEnabled(string $name): bool
    {
        return in_array($name, self::getEnabled());
    }

    public static function getDefault(): ?SmsGateway
    {
        $default = config('sms.default');

        if ($default && self::isEnabled($default)) {
            return self::make($default);
        }

        $enabled = self::getEnabled();

        return $enabled ? self::make($enabled[0]) : null;
    }
}
