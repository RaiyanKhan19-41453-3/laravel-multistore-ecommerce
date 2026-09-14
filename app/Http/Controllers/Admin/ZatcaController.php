<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SubmitZatcaDocument;
use App\Models\ZatcaDevice;
use App\Models\ZatcaDocument;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ZatcaController extends Controller
{
    public function index(): Response
    {
        $documents = ZatcaDocument::with('order:id,order_number')
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

        $device = ZatcaDevice::find(config('zatca.device.serial', 'default'));

        $counts = ZatcaDocument::selectRaw('status, COUNT(*) as total')
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
