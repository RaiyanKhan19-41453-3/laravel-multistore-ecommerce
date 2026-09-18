<?php

namespace App\Services;

class TaxService
{
    public function __construct(
        protected SettingsService $settings = new SettingsService,
    ) {}

    /**
     * Active tax rate in percent. Prefers per-store settings, then
     * generic tax config, then legacy ZATCA config for BC.
     */
    public function rate(?int $storeId = null): float
    {
        $fromSettings = $this->settings?->get('tax.rate', null, $storeId);

        if ($fromSettings !== null && $fromSettings !== '') {
            return (float) $fromSettings;
        }

        $configured = (float) config('tax.rate', 0);

        if ($configured > 0) {
            return $configured;
        }

        return (float) config('zatca.vat_rate', 15.0);
    }

    public function mode(?int $storeId = null): string
    {
        $fromSettings = $this->settings?->get('tax.mode', null, $storeId);

        if (is_string($fromSettings) && $fromSettings !== '') {
            $mode = strtolower($fromSettings);

            // ZATCA requires VAT; a stale "off" setting must not silence it.
            if ($mode === 'off' && config('zatca.enabled', false)) {
                return 'vat';
            }

            return $mode;
        }

        $configured = strtolower((string) config('tax.mode', 'off'));

        // Legacy ZATCA flag wins over the generic default so existing
        // tests and BD deployments keep working unchanged.
        if (config('zatca.enabled', false) && ($configured === '' || $configured === 'off')) {
            return 'vat';
        }

        return $configured !== '' ? $configured : 'off';
    }

    /**
     * Whether tax computation is active for this deployment.
     */
    public function enabled(?int $storeId = null): bool
    {
        return $this->mode($storeId) !== 'off';
    }

    /**
     * ZATCA VAT registration numbers are exactly 15 digits.
     */
    public function isValidVatNumber(?string $vatNumber): bool
    {
        if ($vatNumber === null) {
            return false;
        }

        return preg_match('/^\d{15}$/', trim($vatNumber)) === 1;
    }

    /**
     * Whether the seller tax profile is complete enough to issue invoices.
     */
    public function hasValidSellerProfile(?int $storeId = null): bool
    {
        $seller = $this->sellerProfile($storeId);

        return filled($seller['name_ar'] ?? null)
            && $this->isValidVatNumber($seller['vat_number'] ?? null);
    }

    /**
     * Seller legal identity for invoices: global config with per-store
     * settings overrides (zatca.seller.<field>) winning when present.
     *
     * @return array<string, string>
     */
    public function sellerProfile(?int $storeId = null): array
    {
        $seller = config('zatca.seller', []);

        if (! is_array($seller)) {
            $seller = [];
        }

        foreach (['name_ar', 'name_en', 'vat_number', 'cr_number', 'street', 'building_number', 'city', 'postal_code', 'country'] as $field) {
            $override = $this->settings?->get("zatca.seller.{$field}", null, $storeId);

            if (is_string($override) && $override !== '') {
                $seller[$field] = $override;
            }
        }

        return $seller;
    }

    /**
     * VAT for a taxable base amount, rounded to halalas.
     */
    public function vatFor(float $baseAmount, ?int $storeId = null): float
    {
        if (! $this->enabled($storeId) || $baseAmount <= 0) {
            return 0.0;
        }

        return round($baseAmount * $this->rate($storeId) / 100, 2);
    }

    /**
     * Tax label for receipts (VAT / GST / Tax).
     */
    public function label(): string
    {
        return (string) config('tax.label', 'VAT');
    }

    /**
     * Whether a product's categories exempt it from tax entirely.
     * Zero-rated and exempt slugs are matched case-insensitively.
     * Merges generic tax config with legacy ZATCA lists for BC.
     */
    public function isExempt(?object $product): bool
    {
        if (! $product) {
            return false;
        }

        $slugs = array_merge(
            config('tax.zero_rated_category_slugs', []),
            config('tax.exempt_category_slugs', []),
            config('zatca.zero_rated_category_slugs', []),
            config('zatca.exempt_category_slugs', [])
        );

        if (empty($slugs)) {
            return false;
        }

        $slugs = array_map('strtolower', $slugs);

        foreach ($product->categories ?? [] as $category) {
            if (in_array(strtolower($category->slug ?? ''), $slugs, true)) {
                return true;
            }
        }

        return false;
    }
}
