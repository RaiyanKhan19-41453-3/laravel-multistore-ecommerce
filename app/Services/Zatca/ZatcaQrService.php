<?php

namespace App\Services\Zatca;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * ZATCA QR payloads: TLV-encoded (Tag-Length-Value), Base64 output.
 * Tags 1-5 are mandatory on every invoice; tags 6-8 come from Phase 2
 * signing and tag 9 from ZATCA's clearance/reporting response.
 */
class ZatcaQrService
{
    /**
     * Build the TLV byte string for the given tag => value pairs.
     * Length is the BYTE length of the UTF-8 value, per ZATCA spec.
     */
    public function tlv(array $tags): string
    {
        $binary = '';

        foreach ($tags as $tag => $value) {
            $value = (string) $value;
            $length = strlen($value);

            if ($tag < 1 || $tag > 255 || $length > 255) {
                throw new \InvalidArgumentException("Invalid TLV tag [{$tag}] or value length [{$length}].");
            }

            $binary .= chr($tag).chr($length).$value;
        }

        return $binary;
    }

    public function base64(array $tags): string
    {
        return base64_encode($this->tlv($tags));
    }

    /**
     * Phase 1 QR content: seller name, VAT number, timestamp, total, VAT.
     */
    public function phaseOnePayload(string $sellerName, string $vatNumber, string $timestamp, string $total, string $vatTotal): string
    {
        return $this->base64([
            1 => $sellerName,
            2 => $vatNumber,
            3 => $timestamp,
            4 => $total,
            5 => $vatTotal,
        ]);
    }

    /**
     * Render any payload string as a scannable SVG QR code.
     */
    public function svg(string $payload, int $size = 200, int $margin = 10): string
    {
        $qrCode = new QrCode(
            data: $payload,
            size: $size,
            margin: $margin,
        );

        return (new SvgWriter)->write($qrCode)->getString();
    }
}
