<?php

namespace App\Services;

use App\Models\Order;
use App\Services\Zatca\ZatcaQrService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

class InvoiceService
{
    public function __construct(
        protected ZatcaQrService $qr,
        protected CurrencyService $currency,
        protected TaxService $tax = new TaxService,
    ) {}

    /**
     * Build view data for an order invoice. Never throws — QR is best-effort.
     *
     * @return array{order: Order, lines: Collection, totals: array<string,float>, qrSvg: ?string, qrPayload: ?string, isVat: bool}
     */
    public function dataForOrder(Order $order): array
    {
        $order->loadMissing(['items.product', 'items.productVariant', 'user', 'coupon']);

        $qrSvg = null;
        $qrPayload = null;

        if ((float) $order->tax_amount > 0) {
            try {
                $seller = $this->tax->sellerProfile($order->store_id);
                $sellerName = $seller['name_ar'] ?? null ?: $seller['name_en'] ?? null ?: config('store.name', 'Store');
                $vatNumber = $seller['vat_number'] ?? null ?: '000000000000000';
                $timestamp = $order->created_at?->toIso8601String() ?? now()->toIso8601String();
                $payload = $this->qr->phaseOnePayload($sellerName, $vatNumber, $timestamp, number_format((float) $order->total, 2, '.', ''), number_format((float) $order->tax_amount, 2, '.', ''));
                $qrPayload = $payload;
                $qrSvg = $this->qr->svg($payload, 180);
            } catch (\Throwable) {
                $qrSvg = null;
            }
        }

        $isVat = (float) $order->tax_amount > 0;

        $sellerStoreName = $this->tax->sellerProfile($order->store_id)['name_en'] ?? null
            ?: $this->tax->sellerProfile($order->store_id)['name_ar'] ?? null
            ?: app(SettingsService::class)->get('store.name', null, $order->store_id)
            ?: $order->store?->name
            ?: config('store.name', config('app.name', 'Store'));

        return [
            'order' => $order,
            'lines' => $order->items,
            'totals' => [
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount_total,
                'shipping' => (float) $order->shipping_cost,
                'tax' => (float) $order->tax_amount,
                'grand' => (float) $order->total,
            ],
            'qrSvg' => $qrSvg,
            'qrPayload' => $qrPayload,
            'isVat' => $isVat,
            'store' => [
                'name' => $sellerStoreName,
                'currency' => $this->currency->code(),
            ],
        ];
    }

    public function htmlForOrder(Order $order): string
    {
        $data = $this->dataForOrder($order);

        return View::make('invoices.order', $data)->render();
    }
}
