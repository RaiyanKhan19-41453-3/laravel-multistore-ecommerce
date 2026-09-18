<?php

namespace App\Services\Zatca;

use App\Models\Order;
use App\Models\ZatcaDevice;
use App\Models\ZatcaDocument;
use App\Services\TaxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates ZATCA document lifecycle: numbering (ICV), hash chaining
 * (PIH), UBL generation, signing and Phase 2 QR assembly.
 */
class ZatcaDocumentService
{
    /**
     * Base64 SHA-256 of empty input, used as the chain head for the
     * very first invoice of a device.
     */
    public const GENESIS_HASH = '47DEQpj8HBSa+/TImW+5JCeuQeRkm5NMpJWZG3hSuFU=';

    public function __construct(
        protected UblInvoiceBuilder $builder,
        protected ZatcaSigner $signer,
        protected ZatcaQrService $qr,
        protected TaxService $tax,
    ) {}

    /**
     * Whether the given store can submit documents right now: enabled,
     * seller profile valid, and signing keys available. Null store keeps
     * the legacy deployment-wide check.
     */
    public function canSubmit(?int $storeId = null): bool
    {
        if (! config('zatca.enabled', false) || ! $this->tax->hasValidSellerProfile($storeId)) {
            return false;
        }

        try {
            $this->signingKeys($this->deviceForStore($storeId));
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * This store's EGS device: onboarded unit first, then any unit of the
     * store, then the deployment-wide device. Separate devices keep ICV
     * sequences and PIH chains from crossing stores.
     */
    public function deviceForStore(?int $storeId): ?ZatcaDevice
    {
        try {
            if ($storeId !== null) {
                // Onboarded unit first (NULL datetimes sort unpredictably
                // across drivers), then any unit of the store.
                $onboarded = ZatcaDevice::where('store_id', $storeId)
                    ->whereNotNull('onboarded_at')
                    ->orderBy('serial')
                    ->first();

                if ($onboarded) {
                    return $onboarded;
                }

                $any = ZatcaDevice::where('store_id', $storeId)->orderBy('serial')->first();

                if ($any) {
                    return $any;
                }
            }
        } catch (\Throwable) {
            // Fall through to the global device below.
        }

        try {
            return ZatcaDevice::find(config('zatca.device.serial', 'default'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Signing keypair, preferring the onboarded device over env fallback.
     *
     * @return array{0: string, 1: string} [privateKeyPem, certificatePem]
     *
     * @throws \InvalidArgumentException
     */
    public function signingKeys(?ZatcaDevice $device = null): array
    {
        $device ??= ZatcaDevice::find(config('zatca.device.serial', 'default'));

        if ($device?->isOnboarded() && filled($device->private_key)) {
            return [$device->private_key, $device->certificate];
        }

        $privateKey = config('zatca.device.private_key');
        $certificate = config('zatca.device.certificate');

        if (filled($privateKey) && filled($certificate)) {
            return [$privateKey, $certificate];
        }

        throw new \InvalidArgumentException('No ZATCA signing keys: onboard the device or set ZATCA_PRIVATE_KEY/CERTIFICATE.');
    }

    public function nextIcv(string $deviceSerial): int
    {
        return DB::transaction(function () use ($deviceSerial) {
            $row = DB::table('zatca_counters')
                ->where('device_serial', $deviceSerial)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::table('zatca_counters')->insert([
                    'device_serial' => $deviceSerial,
                    'last_icv' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            DB::table('zatca_counters')
                ->where('device_serial', $deviceSerial)
                ->update(['last_icv' => $row->last_icv + 1, 'updated_at' => now()]);

            return (int) $row->last_icv + 1;
        });
    }

    /**
     * Hash of the most recent document for a device (chain link).
     * Scoped per device: chains must never cross EGS units.
     */
    public function previousHash(?string $deviceSerial = null): string
    {
        $last = ZatcaDocument::whereNotNull('invoice_hash')
            ->when($deviceSerial, fn ($q) => $q->where('device_serial', $deviceSerial))
            ->latest('id')
            ->first();

        return $last?->invoice_hash ?? self::GENESIS_HASH;
    }

    /**
     * Build and persist a draft UBL document for an order.
     *
     * @param  array{name: string, vat_number: string, street: string, city: string, country: string}|null  $buyer
     *
     * @throws \InvalidArgumentException
     */
    public function buildForOrder(Order $order, string $type, ?array $buyer = null): ZatcaDocument
    {
        if (! in_array($type, ['standard', 'simplified'], true)) {
            throw new \InvalidArgumentException("Unknown ZATCA document type [{$type}].");
        }

        if (! $this->tax->hasValidSellerProfile($order->store_id)) {
            throw new \InvalidArgumentException('Seller tax profile is incomplete: Arabic name and 15-digit VAT number are required.');
        }

        $seller = $this->tax->sellerProfile($order->store_id);

        $order->loadMissing(['items', 'items.product']);

        $itemSubtotals = [];

        foreach ($order->items as $item) {
            $itemSubtotals[] = round((float) $item->subtotal, 2);
        }

        $subtotal = round(array_sum($itemSubtotals), 2);
        $discountTotal = round(min(max(0.0, (float) $order->discount_total), $subtotal), 2);
        $shippingCost = round(max(0.0, (float) $order->shipping_cost), 2);

        $lines = [];
        $allocatedDiscount = 0.0;
        $itemCount = count($itemSubtotals);

        foreach ($order->items as $index => $item) {
            // Discounts are allocated pro-rata so the invoice reflects
            // what was actually charged; the last line absorbs rounding.
            $lineDiscount = $subtotal > 0 ? round($discountTotal * ($itemSubtotals[$index] / $subtotal), 2) : 0.0;

            if ($index === $itemCount - 1) {
                $lineDiscount = round($discountTotal - $allocatedDiscount, 2);
            }

            $allocatedDiscount += $lineDiscount;

            $quantity = max(1, (int) $item->quantity);
            $lineTotal = round($itemSubtotals[$index] - $lineDiscount, 2);

            $lines[] = [
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => round($lineTotal / $quantity, 2),
                'line_total' => $lineTotal,
                'vat_rate' => $this->tax->rate($order->store_id),
                'vat_amount' => 0.0,
                'exempt' => $this->tax->isExempt($item->product ?? null),
            ];
        }

        if ($shippingCost > 0) {
            $lines[] = [
                'sku' => 'DELIVERY',
                'name' => 'Delivery',
                'quantity' => 1,
                'unit_price' => $shippingCost,
                'line_total' => $shippingCost,
                'vat_rate' => $this->tax->rate($order->store_id),
                'vat_amount' => 0.0,
                'exempt' => false,
            ];
        }

        $this->allocateVat($order, $lines);

        $uuid = (string) Str::uuid();
        $deviceSerial = $this->deviceForStore($order->store_id)?->serial
            ?? config('zatca.device.serial', 'default')
            ?: 'default';
        $icv = $this->nextIcv($deviceSerial);
        $previousHash = $this->previousHash($deviceSerial);
        $issuedAt = $order->created_at ?? now();

        $xml = $type === 'standard'
            ? $this->builder->buildStandard($order, $seller, $buyer ?? $this->buyerFromOrder($order), $lines, $uuid, $icv, $previousHash, $issuedAt)
            : $this->builder->buildSimplified($order, $seller, $lines, $uuid, $icv, $previousHash, $issuedAt);

        return ZatcaDocument::create([
            'order_id' => $order->id,
            'store_id' => $order->store_id,
            'device_serial' => $deviceSerial,
            'type' => $type,
            'uuid' => $uuid,
            'icv' => $icv,
            'invoice_number' => $order->order_number,
            'previous_invoice_hash' => $previousHash,
            'xml' => $xml,
            'status' => 'draft',
        ]);
    }

    /**
     * Sign a draft document: hash, ECDSA stamp, Phase 2 QR assembly.
     *
     * @throws \RuntimeException|\InvalidArgumentException
     */
    public function signDocument(ZatcaDocument $document, string $privateKeyPem, string $certificatePem): ZatcaDocument
    {
        $hash = $this->signer->invoiceHash($document->xml);
        $signature = $this->signer->sign(base64_decode($hash), $privateKeyPem);
        $publicKey = $this->signer->publicKeyDer($certificatePem);

        $seller = $this->tax->sellerProfile($document->store_id);
        $order = $document->order;

        $qrPayload = $this->qr->base64([
            1 => $seller['name_ar'],
            2 => $seller['vat_number'],
            3 => $document->created_at->toIso8601String(),
            4 => number_format((float) $order->total, 2, '.', ''),
            5 => number_format((float) $order->tax_amount, 2, '.', ''),
            6 => $hash,
            7 => $signature,
            8 => $publicKey,
        ]);

        $document->update([
            'invoice_hash' => $hash,
            'qr_payload' => $qrPayload,
            'status' => 'signed',
        ]);

        return $document->fresh();
    }

    /**
     * Append ZATCA's authority stamp (tag 9) once Fatoora responds to a
     * clearance/reporting submission.
     */
    public function applyAuthorityStamp(ZatcaDocument $document, string $stampB64): ZatcaDocument
    {
        $raw = base64_decode($document->qr_payload, true);

        if ($raw === false) {
            throw new \RuntimeException('Stored QR payload is not valid Base64.');
        }

        $document->update([
            'qr_payload' => base64_encode($raw.chr(9).chr(strlen($stampB64)).$stampB64),
        ]);

        return $document->fresh();
    }

    /**
     * Spread the order's VAT across taxable lines pro-rata so the
     * summed line VAT equals the charged tax exactly (last taxable
     * line absorbs rounding). Exempt lines carry zero VAT. Totals in
     * the XML therefore always reconcile with the order and the QR.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function allocateVat(Order $order, array &$lines): void
    {
        if (! $this->tax->enabled($order->store_id)) {
            return;
        }

        $taxTotal = round((float) $order->tax_amount, 2);

        $pool = [];

        foreach ($lines as $index => $line) {
            if (! ($line['exempt'] ?? false)) {
                $pool[] = $index;
            } else {
                $lines[$index]['vat_rate'] = 0.0;
            }
        }

        if ($taxTotal <= 0 || empty($pool)) {
            return;
        }

        $base = 0.0;

        foreach ($pool as $index) {
            $base += (float) $lines[$index]['line_total'];
        }

        $base = round($base, 2);

        if ($base <= 0) {
            return;
        }

        $allocated = 0.0;
        $last = end($pool);

        foreach ($pool as $index) {
            $share = round($taxTotal * ((float) $lines[$index]['line_total'] / $base), 2);

            if ($index === $last) {
                $share = round($taxTotal - $allocated, 2);
            }

            $allocated += $share;
            $lines[$index]['vat_amount'] = $share;
        }
    }

    private function buyerFromOrder(Order $order): array
    {
        return [
            'name' => $order->shipping_name,
            'vat_number' => '',
            'street' => $order->shipping_address,
            'city' => $order->shipping_city,
            'country' => $order->shipping_country ?? 'SA',
        ];
    }
}
