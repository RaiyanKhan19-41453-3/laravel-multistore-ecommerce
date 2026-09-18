<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SubmitZatcaDocument;
use App\Models\ZatcaDevice;
use App\Models\ZatcaDocument;
use App\Services\Zatca\ZatcaDocumentService;
use App\Support\AdminStoreContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ZatcaController extends Controller
{
    public function index(AdminStoreContext $stores, ZatcaDocumentService $zatca): Response
    {
        $selectedId = $stores->selectedId();

        $documentsQuery = $stores->scope(ZatcaDocument::with('order:id,order_number'));

        $documents = $documentsQuery
            ->latest()
            ->paginate(20)
            ->through(fn (ZatcaDocument $document) => [
                'id' => $document->id,
                'order_number' => $document->order?->order_number,
                'type' => $document->type,
                'uuid' => $document->uuid,
                'icv' => $document->icv,
                'status' => $document->status,
                'submit_attempts' => $document->submit_attempts,
                'submitted_at' => $document->submitted_at,
                'created_at' => $document->created_at,
            ]);

        $device = $zatca->deviceForStore($selectedId)
            ?? ZatcaDevice::find(config('zatca.device.serial', 'default'));

        $countsQuery = $stores->scope(ZatcaDocument::query());

        $counts = $countsQuery->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('admin/zatca/index', [
            'documents' => $documents,
            'counts' => $counts,
            'device' => $device ? [
                'serial' => $device->serial,
                'onboarded' => $device->isOnboarded(),
                'onboarded_at' => $device->onboarded_at,
                'has_private_key' => filled($device->private_key),
            ] : null,
            'enabled' => config('zatca.enabled', false),
            'sandbox' => config('zatca.sandbox', true),
        ]);
    }

    public function retry(ZatcaDocument $document): RedirectResponse
    {
        if ($document->isFinal()) {
            return back()->withErrors(['document' => 'Document is already finalized.']);
        }

        SubmitZatcaDocument::dispatch($document->id);

        return back()->with('success', "Document #{$document->id} re-queued for submission.");
    }
}
