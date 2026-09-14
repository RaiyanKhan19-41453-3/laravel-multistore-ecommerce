<?php

namespace App\Services\Zatca;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal client for ZATCA's Fatoora platform (sandbox + production).
 * Endpoint paths follow the published integration guides and are
 * overrideable via config when ZATCA versions them.
 */
class FatooraClient
{
    public function __construct(
        protected ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? config('zatca.api_base'), '/');
    }

    protected function endpoints(): array
    {
        return config('zatca.endpoints', [
            'compliance' => '/compliance',
            'production_csids' => '/production/csids',
            'clearance' => '/invoices/clearance/single',
            'reporting' => '/invoices/reporting/single',
        ]);
    }

    protected function basicAuth(string $username, string $password): string
    {
        return 'Basic '.base64_encode("{$username}:{$password}");
    }

    protected function baseHeaders(string $auth): array
    {
        return [
            'Accept' => 'application/json',
            'Accept-Version' => 'V2',
            'Accept-Language' => 'en',
            'Content-Type' => 'application/json',
            'Authorization' => $auth,
        ];
    }

    /**
     * Step 1 of onboarding: exchange a CSR + portal OTP for a compliance CSID.
     *
     * @return array{binarySecurityToken: string, secret: string, requestID: string}
     *
     * @throws \RuntimeException
     */
    public function requestComplianceCsid(string $csr, string $otp): array
    {
        try {
            $response = Http::withHeaders($this->baseHeaders($this->basicAuth($otp, '')))
                ->timeout(30)
                ->post($this->baseUrl.$this->endpoints()['compliance'], ['csr' => $csr]);

            $data = $response->json();

            if (! $response->successful() || empty($data['binarySecurityToken'])) {
                Log::warning('Fatoora compliance CSID failed', ['response' => $data]);

                throw new \RuntimeException('Compliance CSID rejected: '.($data['message'] ?? $data['error'] ?? 'unknown error'));
            }

            return $data;
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Fatoora unreachable: '.$e->getMessage());
        }
    }

    /**
     * Step 2 of onboarding: exchange the compliance request ID for the
     * production CSID used to sign and submit live invoices.
     *
     * @return array{binarySecurityToken: string, secret: string, requestID: string}
     *
     * @throws \RuntimeException
     */
    public function requestProductionCsid(string $complianceRequestId, string $complianceToken, string $complianceSecret): array
    {
        try {
            $response = Http::withHeaders($this->baseHeaders($this->basicAuth($complianceToken, $complianceSecret)))
                ->timeout(30)
                ->post($this->baseUrl.$this->endpoints()['production_csids'], [
                    'compliance_request_id' => $complianceRequestId,
                ]);

            $data = $response->json();

            if (! $response->successful() || empty($data['binarySecurityToken'])) {
                Log::warning('Fatoora production CSID failed', ['response' => $data]);

                throw new \RuntimeException('Production CSID rejected: '.($data['message'] ?? $data['error'] ?? 'unknown error'));
            }

            return $data;
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Fatoora unreachable: '.$e->getMessage());
        }
    }

    /**
     * Clear a standard (B2B) invoice. Must succeed BEFORE the buyer sees it.
     *
     * @return array{cleared: bool, stamp: ?string, warnings: array, errors: array, raw: array}
     */
    public function clearance(string $invoiceHash, string $uuid, string $xmlBase64, string $token, string $secret): array
    {
        return $this->submit('clearance', $invoiceHash, $uuid, $xmlBase64, $token, $secret);
    }

    /**
     * Report a simplified (B2C) invoice within 24 hours of issuance.
     *
     * @return array{cleared: bool, stamp: ?string, warnings: array, errors: array, raw: array}
     */
    public function reporting(string $invoiceHash, string $uuid, string $xmlBase64, string $token, string $secret): array
    {
        return $this->submit('reporting', $invoiceHash, $uuid, $xmlBase64, $token, $secret);
    }

    protected function submit(string $kind, string $invoiceHash, string $uuid, string $xmlBase64, string $token, string $secret): array
    {
        try {
            $response = Http::withHeaders($this->baseHeaders($this->basicAuth($token, $secret)))
                ->timeout(30)
                ->post($this->baseUrl.$this->endpoints()[$kind], [
                    'invoiceHash' => $invoiceHash,
                    'uuid' => $uuid,
                    'invoice' => $xmlBase64,
                ]);

            $data = $response->json() ?? [];
            $status = strtoupper((string) ($data['validationResults']['status'] ?? ''));

            $cleared = $response->successful() && in_array($status, ['PASS', 'PASS_WITH_WARNINGS'], true);

            return [
                'cleared' => $cleared,
                'stamp' => $data['stamp'] ?? $data['cryptographicStamp'] ?? null,
                'warnings' => $data['validationResults']['warningMessages'] ?? [],
                'errors' => $data['validationResults']['errorMessages'] ?? [],
                'raw' => $data,
            ];
        } catch (ConnectionException $e) {
            Log::warning("Fatoora {$kind} unreachable", ['uuid' => $uuid, 'error' => $e->getMessage()]);

            return ['cleared' => false, 'stamp' => null, 'warnings' => [], 'errors' => [$e->getMessage()], 'raw' => []];
        }
    }
}
