<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\AdminStoreContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $this->parseDate($request->query('from'), now()->subDays(30)->startOfDay(), true);
        $to = $this->parseDate($request->query('to'), now()->endOfDay(), false);

        $paidStatuses = ['confirmed', 'processing', 'shipped', 'delivered', 'completed'];
        $storeId = app(AdminStoreContext::class)->selectedId();

        $scope = fn ($q) => $storeId ? $q->where('orders.store_id', $storeId) : $q;

        $salesDaily = $scope(Order::whereIn('orders.status', $paidStatuses)
            ->whereBetween('orders.created_at', [$from, $to]))
            ->selectRaw('DATE(orders.created_at) as date, COUNT(*) as orders, SUM(orders.total) as revenue, SUM(orders.tax_amount) as vat, SUM(orders.discount_total) as discounts, SUM(orders.shipping_cost) as shipping')
            ->groupByRaw('DATE(orders.created_at)')
            ->orderBy('date')
            ->get();

        $totals = [
            'orders' => $salesDaily->sum('orders'),
            'revenue' => (float) $salesDaily->sum('revenue'),
            'vat' => (float) $salesDaily->sum('vat'),
            'discounts' => (float) $salesDaily->sum('discounts'),
            'shipping' => (float) $salesDaily->sum('shipping'),
        ];

        $byPayment = $scope(Order::whereIn('orders.status', $paidStatuses)
            ->whereBetween('orders.created_at', [$from, $to]))
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('payments.status', 'paid')
            ->selectRaw('payments.method as method, COUNT(DISTINCT orders.id) as orders, SUM(orders.total) as revenue')
            ->groupBy('payments.method')
            ->get();

        $byCoupon = $scope(Order::whereIn('orders.status', $paidStatuses)
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotNull('orders.coupon_code'))
            ->selectRaw('orders.coupon_code as coupon_code, COUNT(*) as orders, SUM(orders.discount_total) as discount_given, SUM(orders.total) as revenue')
            ->groupBy('orders.coupon_code')
            ->get();

        $byShipping = $scope(Order::whereIn('orders.status', $paidStatuses)
            ->whereBetween('orders.created_at', [$from, $to]))
            ->selectRaw('orders.shipping_method_name as shipping_method_name, COUNT(*) as orders, SUM(orders.shipping_cost) as shipping_revenue')
            ->groupBy('orders.shipping_method_name')
            ->get();

        return Inertia::render('admin/reports/index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => $totals,
            'salesDaily' => $salesDaily,
            'byPayment' => $byPayment,
            'byCoupon' => $byCoupon,
            'byShipping' => $byShipping,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $type = $request->query('type', 'sales');
        $from = $this->parseDate($request->query('from'), now()->subDays(30)->startOfDay(), true);
        $to = $this->parseDate($request->query('to'), now()->endOfDay(), false);

        $filename = "report-{$type}-{$from->toDateString()}_to_{$to->toDateString()}.csv";
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($type, $from, $to) {
            $out = fopen('php://output', 'w');

            match ($type) {
                'vat' => $this->exportVat($out, $from, $to),
                'coupons' => $this->exportCoupons($out, $from, $to),
                'shipping' => $this->exportShipping($out, $from, $to),
                'payments' => $this->exportPayments($out, $from, $to),
                default => $this->exportSales($out, $from, $to),
            };

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Parse an admin-supplied date, falling back to the default window on
     * garbage input instead of blowing up with a 500.
     */
    private function parseDate(mixed $value, Carbon $default, bool $startOfDay): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        try {
            $date = now()->parse($value);
        } catch (\Throwable) {
            return $default;
        }

        return $startOfDay ? $date->startOfDay() : $date->endOfDay();
    }

    private function exportSales($out, $from, $to): void
    {
        $storeId = app(AdminStoreContext::class)->selectedId();
        fputcsv($out, ['Date', 'Orders', 'Revenue', 'VAT', 'Discounts', 'Shipping']);
        $query = Order::whereIn('orders.status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])
            ->whereBetween('orders.created_at', [$from, $to])
            ->when($storeId, fn ($q) => $q->where('orders.store_id', $storeId))
            ->selectRaw('DATE(orders.created_at) as date, COUNT(*) as orders, SUM(orders.total) as revenue, SUM(orders.tax_amount) as vat, SUM(orders.discount_total) as discounts, SUM(orders.shipping_cost) as shipping')
            ->groupByRaw('DATE(orders.created_at)')
            ->orderBy('date');
        $query->chunk(200, function ($rows) use ($out) {
            foreach ($rows as $r) {
                fputcsv($out, [$r->date, $r->orders, $r->revenue, $r->vat, $r->discounts, $r->shipping]);
            }
        });
    }

    private function exportVat($out, $from, $to): void
    {
        $storeId = app(AdminStoreContext::class)->selectedId();
        fputcsv($out, ['Date', 'Order Number', 'Subtotal', 'VAT', 'Total', 'Status']);
        Order::whereBetween('orders.created_at', [$from, $to])
            ->where('orders.tax_amount', '>', 0)
            ->when($storeId, fn ($q) => $q->where('orders.store_id', $storeId))
            ->orderBy('orders.created_at')
            ->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $o) {
                    fputcsv($out, [$o->created_at->toDateString(), $o->order_number, $o->subtotal, $o->tax_amount, $o->total, $o->status]);
                }
            });
    }

    private function exportCoupons($out, $from, $to): void
    {
        $storeId = app(AdminStoreContext::class)->selectedId();
        fputcsv($out, ['Coupon', 'Orders', 'Discount Given', 'Revenue']);
        Order::whereIn('orders.status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotNull('orders.coupon_code')
            ->when($storeId, fn ($q) => $q->where('orders.store_id', $storeId))
            ->selectRaw('orders.coupon_code as coupon_code, COUNT(*) as orders, SUM(orders.discount_total) as discount_given, SUM(orders.total) as revenue')
            ->groupBy('orders.coupon_code')
            ->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->coupon_code, $r->orders, $r->discount_given, $r->revenue]);
                }
            });
    }

    private function exportShipping($out, $from, $to): void
    {
        $storeId = app(AdminStoreContext::class)->selectedId();
        fputcsv($out, ['Shipping Method', 'Orders', 'Shipping Revenue']);
        Order::whereIn('orders.status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])
            ->whereBetween('orders.created_at', [$from, $to])
            ->when($storeId, fn ($q) => $q->where('orders.store_id', $storeId))
            ->selectRaw('orders.shipping_method_name as shipping_method_name, COUNT(*) as orders, SUM(orders.shipping_cost) as shipping_revenue')
            ->groupBy('orders.shipping_method_name')
            ->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->shipping_method_name ?? '-', $r->orders, $r->shipping_revenue]);
                }
            });
    }

    private function exportPayments($out, $from, $to): void
    {
        $storeId = app(AdminStoreContext::class)->selectedId();
        fputcsv($out, ['Payment Method', 'Orders', 'Revenue']);
        Order::whereIn('orders.status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])
            ->whereBetween('orders.created_at', [$from, $to])
            ->when($storeId, fn ($q) => $q->where('orders.store_id', $storeId))
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('payments.status', 'paid')
            ->selectRaw('payments.method as method, COUNT(DISTINCT orders.id) as orders, SUM(orders.total) as revenue')
            ->groupBy('payments.method')
            ->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->method, $r->orders, $r->revenue]);
                }
            });
    }
}
