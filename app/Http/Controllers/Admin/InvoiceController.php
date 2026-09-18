<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InvoiceService;
use Dompdf\Dompdf;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoices,
    ) {}

    public function show(Order $order): \Inertia\Response
    {
        $data = $this->invoices->dataForOrder($order);

        return Inertia::render('admin/orders/invoice', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'created_at' => $order->created_at?->toIso8601String(),
                'shipping_name' => $order->shipping_name,
                'shipping_phone' => $order->shipping_phone,
                'shipping_address' => $order->shipping_address,
                'shipping_city' => $order->shipping_city,
                'shipping_state' => $order->shipping_state,
                'shipping_country' => $order->shipping_country,
                'shipping_postal_code' => $order->shipping_postal_code,
                'shipping_method_name' => $order->shipping_method_name,
                'coupon_code' => $order->coupon_code,
                'notes' => $order->notes,
            ],
            'lines' => $data['lines']->map(fn ($l) => [
                'name' => $l->name,
                'sku' => $l->sku,
                'quantity' => $l->quantity,
                'unit_price' => $l->unit_price,
                'total' => $l->total,
            ]),
            'totals' => $data['totals'],
            'qrSvg' => $data['qrSvg'],
            'qrPayload' => $data['qrPayload'],
            'isVat' => $data['isVat'],
            'store' => $data['store'],
            'htmlUrl' => route('admin.invoices.print', $order),
            'pdfUrl' => route('admin.invoices.pdf', $order),
        ]);
    }

    public function print(Order $order): Response
    {
        $html = $this->invoices->htmlForOrder($order);

        return response($html)->header('Content-Type', 'text/html');
    }

    public function pdf(Order $order): Response|StreamedResponse
    {
        $html = $this->invoices->htmlForOrder($order);

        if (! class_exists(Dompdf::class)) {
            return response($html)->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'inline; filename="invoice-'.$order->order_number.'.html"');
        }

        $dompdf = new Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.$order->order_number.'.pdf"',
        ]);
    }

    public function packingSlip(Order $order): Response
    {
        $order->loadMissing(['items.product']);
        $html = view('invoices.packing', ['order' => $order, 'store' => ['name' => config('store.name', config('app.name', 'Store'))]])->render();

        return response($html)->header('Content-Type', 'text/html');
    }
}
