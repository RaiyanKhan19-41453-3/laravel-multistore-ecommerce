<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
        protected CurrencyService $currency,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/settings/index', [
            'settings' => $this->settings->all(),
            'presets' => SettingsService::presets(),
            'currencies' => array_keys(CurrencyService::supported()),
            'taxModes' => ['off', 'vat', 'gst'],
            'locales' => ['en', 'ar'],
            'payments' => [
                'cod' => 'Cash on Delivery',
                'sslcommerz' => 'SSLCommerz (BD)',
                'bkash' => 'bKash (BD)',
                'moyasar' => 'Moyasar: Mada / Cards (SA)',
                'tabby' => 'Tabby: Pay in 4 (SA)',
                'stripe' => 'Stripe: International Cards',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store.name' => 'nullable|string|max:255',
            'store.country' => 'nullable|string|size:2',
            'store.currency' => 'nullable|string|in:BDT,SAR,USD,EUR,AED,INR',
            'store.locale' => 'nullable|string|in:en,ar',
            'store.timezone' => 'nullable|string|max:64',
            'tax.mode' => 'nullable|string|in:off,vat,gst',
            'tax.rate' => 'nullable|numeric|min:0|max:100',
            'tax.label' => 'nullable|string|max:16',
            'payment.cod_enabled' => 'nullable|boolean',
            'payment.sslcommerz_enabled' => 'nullable|boolean',
            'payment.bkash_enabled' => 'nullable|boolean',
            'payment.moyasar_enabled' => 'nullable|boolean',
            'payment.tabby_enabled' => 'nullable|boolean',
            'payment.stripe_enabled' => 'nullable|boolean',
            'notifications.mail_enabled' => 'nullable|boolean',
            'notifications.sms_enabled' => 'nullable|boolean',
            'zatca.seller.name_ar' => 'nullable|string|max:255',
            'zatca.seller.name_en' => 'nullable|string|max:255',
            'zatca.seller.vat_number' => 'nullable|string|max:32',
            'zatca.seller.cr_number' => 'nullable|string|max:32',
            'zatca.seller.street' => 'nullable|string|max:255',
            'zatca.seller.building_number' => 'nullable|string|max:32',
            'zatca.seller.city' => 'nullable|string|max:100',
            'zatca.seller.postal_code' => 'nullable|string|max:32',
            'zatca.seller.country' => 'nullable|string|size:2',
        ]);

        // Flatten nested store/tax arrays into dotted setting keys.
        $flat = Arr::dot($validated);
        $grouped = [];

        foreach ($flat as $key => $value) {
            [$group] = explode('.', $key, 2) + [null];
            $stringValue = is_bool($value) ? ($value ? '1' : '0') : ($value === null ? null : (string) $value);
            $grouped[$group ?? 'general'][$key] = $stringValue;
        }

        foreach ($grouped as $group => $values) {
            if (! empty($values)) {
                $this->settings->setMany($values, $group);
            }
        }

        return to_route('admin.settings.index')->with('success', __('store.saved'));
    }

    public function applyPreset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country' => 'required|string|in:BD,SA',
        ]);

        $this->settings->applyPreset($validated['country']);

        return to_route('admin.settings.index')->with(
            'success',
            __('store.preset_applied', ['country' => $validated['country']])
        );
    }
}
