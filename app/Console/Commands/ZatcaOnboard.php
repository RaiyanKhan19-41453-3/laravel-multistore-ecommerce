<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\ZatcaDevice;
use App\Services\TaxService;
use App\Services\Zatca\FatooraClient;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

/**
 * Interactive EGS onboarding with Fatoora:
 * keypair + CSR -> compliance CSID (portal OTP) -> production CSID.
 * Run against sandbox first; production only after sandbox certification.
 */
class ZatcaOnboard extends Command
{
    protected $signature = 'zatca:onboard
        {--serial= : Device serial (defaults to ZATCA_DEVICE_SERIAL)}
        {--store= : Store slug to own this device (per-store EGS unit)}
        {--show-csr : Print the CSR and stop before requesting OTP}';

    protected $description = 'Onboard this EGS unit with ZATCA Fatoora (compliance + production CSID)';

    public function handle(FatooraClient $fatoora): int
    {
        $serial = $this->option('serial') ?: config('zatca.device.serial', 'default');

        if (! $serial) {
            $this->error('Set ZATCA_DEVICE_SERIAL first.');

            return self::FAILURE;
        }

        $storeId = null;

        if ($slug = $this->option('store')) {
            $store = Store::where('slug', $slug)->first();

            if (! $store) {
                $this->error("Store [{$slug}] not found.");

                return self::FAILURE;
            }

            $storeId = $store->id;
        }

        $device = ZatcaDevice::firstOrNew(['serial' => $serial]);

        if ($storeId !== null) {
            $device->store_id = $storeId;
        }

        if (! $device->private_key) {
            $this->info('Generating secp256k1 keypair and CSR...');
            [$privateKey, $csr] = $this->generateCsr($serial, $storeId);
            $device->private_key = $privateKey;
            $device->csr = $csr;
            $device->save();
        }

        $this->line('Submit this CSR in the Fatoora portal to get an OTP:');
        $this->line((string) $device->csr);

        if ($this->option('show-csr')) {
            return self::SUCCESS;
        }

        $otp = text('Portal OTP', required: true);

        $this->info('Requesting compliance CSID...');
        $compliance = $fatoora->requestComplianceCsid((string) $device->csr, $otp);

        $device->compliance_request_id = $compliance['requestID'] ?? null;
        $device->csid = $compliance['binarySecurityToken'];
        $device->csid_secret = $compliance['secret'] ?? null;
        $device->save();

        $this->info('Requesting production CSID...');
        $production = $fatoora->requestProductionCsid(
            (string) $device->compliance_request_id,
            (string) $device->csid,
            (string) $device->csid_secret,
        );

        $device->csid = $production['binarySecurityToken'];
        $device->csid_secret = $production['secret'] ?? null;
        $device->certificate = $production['certificate'] ?? $device->certificate;
        $device->onboarded_at = now();
        $device->save();

        $this->info("Device [{$serial}] onboarded successfully.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string} [privateKeyPem, csrPem]
     */
    protected function generateCsr(string $serial, ?int $storeId = null): array
    {
        $key = openssl_pkey_new([
            'curve_name' => 'secp256k1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($key === false) {
            throw new \RuntimeException('EC key generation failed.');
        }

        openssl_pkey_export($key, $privatePem);

        $seller = app(TaxService::class)->sellerProfile($storeId);

        $dn = array_filter([
            'countryName' => $seller['country'] ?? 'SA',
            'organizationName' => $seller['name_en'] ?? config('app.name'),
            'organizationalUnitName' => 'IT',
            'commonName' => $serial,
            'serialNumber' => $serial,
        ]);

        $csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);

        if ($csr === false) {
            throw new \RuntimeException('CSR generation failed: '.openssl_error_string());
        }

        openssl_csr_export($csr, $csrPem);

        return [$privatePem, $csrPem];
    }
}
