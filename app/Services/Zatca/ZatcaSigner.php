<?php

namespace App\Services\Zatca;

/**
 * ECDSA (secp256k1) signing for Phase 2 invoices using PHP's openssl
 * extension. Keys come from the ZATCA-issued CSID certificate.
 */
class ZatcaSigner
{
    /**
     * Canonicalize XML (C14N) so hashing and signing are stable.
     *
     * @throws \RuntimeException
     */
    public function canonicalize(string $xml): string
    {
        $doc = new \DOMDocument;
        $doc->preserveWhiteSpace = false;

        if (! @$doc->loadXML($xml)) {
            throw new \RuntimeException('Cannot parse XML for canonicalization.');
        }

        $canonical = $doc->C14N(true, false);

        if ($canonical === false) {
            throw new \RuntimeException('XML canonicalization failed.');
        }

        return $canonical;
    }

    /**
     * Base64 SHA-256 hash of the canonical invoice XML.
     */
    public function invoiceHash(string $xml): string
    {
        return base64_encode(hash('sha256', $this->canonicalize($xml), true));
    }

    /**
     * Sign data with an EC private key. Returns Base64 DER signature.
     *
     * @throws \RuntimeException
     */
    public function sign(string $data, string $privateKeyPem): string
    {
        $key = openssl_pkey_get_private(trim($privateKeyPem));

        if ($key === false) {
            throw new \RuntimeException('Invalid EC private key: '.openssl_error_string());
        }

        if (! openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('ECDSA signing failed: '.openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * Base64 DER-encoded public key extracted from an X.509 certificate.
     *
     * @throws \RuntimeException
     */
    public function publicKeyDer(string $certificatePem): string
    {
        $cert = openssl_x509_read(trim($certificatePem));

        if ($cert === false) {
            throw new \RuntimeException('Invalid X.509 certificate.');
        }

        $pubKey = openssl_pkey_get_public($cert);

        if ($pubKey === false) {
            throw new \RuntimeException('Cannot extract public key from certificate.');
        }

        $details = openssl_pkey_get_details($pubKey);

        if (! isset($details['key'])) {
            throw new \RuntimeException('Cannot read public key details.');
        }

        $der = base64_decode(preg_replace(
            ['/-----BEGIN PUBLIC KEY-----/', '/-----END PUBLIC KEY-----/', '/\s+/'],
            '',
            $details['key']
        ));

        if ($der === false) {
            throw new \RuntimeException('Cannot DER-encode public key.');
        }

        return base64_encode($der);
    }

    /**
     * Verify a Base64 DER signature against data and a public key.
     */
    public function verify(string $data, string $signatureB64, mixed $publicKey): bool
    {
        $signature = base64_decode($signatureB64, true);

        if ($signature === false) {
            return false;
        }

        return openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }
}
