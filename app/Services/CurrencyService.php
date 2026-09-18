<?php

namespace App\Services;

use App\Support\CurrentStore;

class CurrencyService
{
    /**
     * @return array<string, array{symbol: string, locale: string, decimals: int}>
     */
    public static function supported(): array
    {
        return [
            'BDT' => ['symbol' => '৳', 'locale' => 'en-US', 'decimals' => 0],
            'SAR' => ['symbol' => 'ر.س', 'locale' => 'ar-SA', 'decimals' => 2],
            'USD' => ['symbol' => '$', 'locale' => 'en-US', 'decimals' => 2],
            'EUR' => ['symbol' => '€', 'locale' => 'de-DE', 'decimals' => 2],
            'AED' => ['symbol' => 'د.إ', 'locale' => 'ar-AE', 'decimals' => 2],
            'INR' => ['symbol' => '₹', 'locale' => 'en-IN', 'decimals' => 0],
        ];
    }

    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function code(): string
    {
        $code = strtoupper((string) ($this->settings->get('store.currency') ?? $this->storeCurrency() ?? config('store.currency', 'BDT')));

        return self::supported()[$code] ?? false ? $code : 'BDT';
    }

    public function symbol(?string $code = null): string
    {
        $code = $code ? strtoupper($code) : $this->code();

        return self::supported()[$code]['symbol'] ?? $code.' ';
    }

    private function storeCurrency(): ?string
    {
        try {
            $store = app(CurrentStore::class)->get()
                ?? app(CurrentStore::class)->default();

            return $store?->currency;
        } catch (\Throwable) {
            return null;
        }
    }

    public function format(float|string $amount, ?string $code = null): string
    {
        $code = $code ? strtoupper($code) : $this->code();
        $meta = self::supported()[$code] ?? self::supported()['BDT'];
        $number = (float) $amount;

        if (class_exists(\NumberFormatter::class)) {
            try {
                $formatter = new \NumberFormatter($meta['locale'], \NumberFormatter::DECIMAL);
                $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $meta['decimals']);
                $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, max($meta['decimals'], 2));

                $formatted = $formatter->format($number);

                if ($formatted !== false) {
                    return $meta['symbol'].$formatted;
                }
            } catch (\Throwable) {
                // Fall through to plain formatting.
            }
        }

        return $meta['symbol'].number_format($number, $meta['decimals'], '.', ',');
    }
}
