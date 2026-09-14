<?php

namespace App\Services;

class TaxService
{
    /**
     * Standard VAT rate in percent (e.g. 15.0 for Saudi Arabia).
     */
    public function rate(): float
    {
        return (float) config('zatca.vat_rate', 15.0);
    }

    /**
     * Whether tax computation is active for this deployment.
     */
    public function enabled(): bool
    {
        return (bool) config('zatca.enabled', false);
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
    public function hasValidSellerProfile(): bool
    {
        $seller = config('zatca.seller', []);

        return filled($seller['name_ar'] ?? null)
            && $this->isValidVatNumber($seller['vat_number'] ?? null);
    }

    /**
     * VAT for a taxable base amount, rounded to halalas.
     */
    public function vatFor(float $baseAmount): float
    {
        if (! $this->enabled() || $baseAmount <= 0) {
            return 0.0;
        }

        return round($baseAmount * $this->rate() / 100, 2);
    }

    /**
     * Whether a product's categories exempt it from VAT entirely.
     * Zero-rated and exempt slugs are matched case-insensitively.
     */
    public function isExempt(?object $product): bool
    {
        if (! $product) {
            return false;
        }

        $slugs = array_merge(
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
