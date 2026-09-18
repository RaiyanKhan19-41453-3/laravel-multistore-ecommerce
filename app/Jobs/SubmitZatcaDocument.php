<?php

namespace App\Jobs;

use App\Models\ZatcaDevice;
use App\Models\ZatcaDocument;
use App\Services\Zatca\FatooraClient;
use App\Services\Zatca\ZatcaDocumentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class SubmitZatcaDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $documentId,
    ) {}

    /**
     * Serialize submissions per device: Fatoora validates every document
     * against the current chain head, so overlapping submissions for the
     * same device can only corrupt the chain. Unlike ShouldBeUnique this
     * queues distinct documents instead of dropping them.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $serial = ZatcaDocument::whereKey($this->documentId)->value('device_serial');

        return [new WithoutOverlapping('zatca-device-'.($serial ?: 'default'), releaseAfter: 30, expiresAfter: 600)];
    }

    public function tries(): int
    {
        return max(1, config('zatca.reporting_max_attempts', 10));
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600, 10800];
    }

    public function handle(ZatcaDocumentService $documents, FatooraClient $fatoora): void
    {
        $document = ZatcaDocument::find($this->documentId);

        if (! $document || $document->isFinal()) {
            return;
        }

        if (! config('zatca.enabled', false)) {
            return;
        }

        $device = $document->device_serial
            ? ZatcaDevice::find($document->device_serial)
            : null;
        $device ??= ZatcaDevice::find(config('zatca.device.serial', 'default'));

        if (! $device?->isOnboarded()) {
            Log::warning('ZATCA submit skipped: device not onboarded', ['document_id' => $document->id]);
            $this->fail('Device not onboarded with Fatoora.');

            return;
        }

        if ($document->status === 'draft') {
            [$privateKey, $certificate] = $documents->signingKeys($device);
            $document = $documents->signDocument($document, $privateKey, $certificate);
        }

        $document->increment('submit_attempts');
        $document->update(['submitted_at' => now()]);

        $result = $document->type === 'standard'
            ? $fatoora->clearance($document->invoice_hash, $document->uuid, base64_encode($document->xml), $device->csid, $device->csid_secret)
            : $fatoora->reporting($document->invoice_hash, $document->uuid, base64_encode($document->xml), $device->csid, $device->csid_secret);

        $document->update(['gateway_response' => $result['raw']]);

        if ($result['cleared']) {
            if ($result['stamp']) {
                $documents->applyAuthorityStamp($document->fresh(), $result['stamp']);
            }

            $document->update(['status' => $document->type === 'standard' ? 'cleared' : 'reported']);

            if (! empty($result['warnings'])) {
                Log::warning('ZATCA accepted with warnings', [
                    'document_id' => $document->id,
                    'warnings' => $result['warnings'],
                ]);
            }

            return;
        }

        Log::warning('ZATCA submission rejected, will retry', [
            'document_id' => $document->id,
            'errors' => $result['errors'],
        ]);

        throw new \RuntimeException('Fatoora rejected document #'.$document->id.': '.implode('; ', array_slice($result['errors'], 0, 3)));
    }

    public function failed(\Throwable $e): void
    {
        $document = ZatcaDocument::find($this->documentId);

        if ($document && ! $document->isFinal()) {
            $document->update(['status' => 'failed']);
        }

        Log::error('ZATCA submission exhausted retries', [
            'document_id' => $this->documentId,
            'error' => $e->getMessage(),
        ]);
    }
}
