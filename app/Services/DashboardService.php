<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\ZatcaDocument;
use App\Support\AdminStoreContext;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public function stats(): array
    {
        $storeId = $this->storeId();
        $key = $storeId ? "dashboard.stats.store:{$storeId}:v1" : 'dashboard.stats.v1';

        return Cache::remember($key, 300, function () use ($storeId) {
            $paidStatuses = ['confirmed', 'processing', 'shipped', 'delivered', 'completed'];

            $orders = $storeId ? Order::where('store_id', $storeId) : Order::query();
            $paidOrders = (clone $orders)->whereIn('status', $paidStatuses);

            $totalRevenue = (float) (clone $paidOrders)->sum('total');
            $totalOrders = (clone $orders)->count();
            $pendingOrders = (clone $orders)->where('status', 'pending')->count();
            $cancelledOrders = (clone $orders)->where('status', 'cancelled')->count();

            $todayRevenue = (float) (clone $paidOrders)
                ->whereDate('created_at', today())
                ->sum('total');

            $weekRevenue = (float) (clone $paidOrders)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('total');

            $monthRevenue = (float) (clone $paidOrders)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total');

            $aov = 0;

            if ($totalOrders > 0) {
                $paidCount = (clone $paidOrders)->count();
                $aov = $paidCount > 0 ? round($totalRevenue / $paidCount, 2) : 0;
            }

            $inventories = $storeId ? Inventory::where('store_id', $storeId) : Inventory::query();
            $products = $storeId ? Product::where('store_id', $storeId) : Product::query();

            $lowStockCount = (clone $inventories)->where('quantity', '>', 0)
                ->where('quantity', '<=', 5)
                ->count();

            $outOfStockCount = (clone $inventories)->where('quantity', '<=', 0)->count();

            $totalProducts = (clone $products)->count();
            $activeProducts = (clone $products)->where('is_active', true)->count();

            $totalCustomers = $storeId
                ? User::whereHas('orders', fn ($q) => $q->where('orders.store_id', $storeId))
                    ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super-admin'))->count()
                : User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'super-admin'))->count();

            $zatcaPending = 0;
            $zatcaFailed = 0;
            try {
                if (Schema::hasTable('zatca_documents')) {
                    $zatcaQuery = $storeId ? ZatcaDocument::where('store_id', $storeId) : ZatcaDocument::query();
                    $zatcaPending = (clone $zatcaQuery)->where('status', 'pending')->count();
                    $zatcaFailed = (clone $zatcaQuery)->where('status', 'failed')->count();
                }
            } catch (\Throwable) {
                // ignore
            }

            return [
                'totalRevenue' => $totalRevenue,
                'todayRevenue' => $todayRevenue,
                'weekRevenue' => $weekRevenue,
                'monthRevenue' => $monthRevenue,
                'totalOrders' => $totalOrders,
                'pendingOrders' => $pendingOrders,
                'cancelledOrders' => $cancelledOrders,
                'aov' => $aov,
                'lowStockCount' => $lowStockCount,
                'outOfStockCount' => $outOfStockCount,
                'totalProducts' => $totalProducts,
                'activeProducts' => $activeProducts,
                'totalCustomers' => $totalCustomers,
                'zatcaPending' => $zatcaPending,
                'zatcaFailed' => $zatcaFailed,
            ];
        });
    }

    public function chartData(int $days = 14): array
    {
        $paidStatuses = ['confirmed', 'processing', 'shipped', 'delivered', 'completed'];
        $start = now()->subDays($days - 1)->startOfDay();
        $storeId = $this->storeId();

        $query = Order::whereIn('status', $paidStatuses)
            ->where('created_at', '>=', $start);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $rows = $query
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $row = $rows[$date] ?? null;
            $data[] = [
                'date' => $date,
                'label' => $start->copy()->addDays($i)->format('M d'),
                'orders' => $row ? (int) $row->orders : 0,
                'revenue' => $row ? (float) $row->revenue : 0,
            ];
        }

        return $data;
    }

    public function topProducts(int $limit = 5): array
    {
        $paidStatuses = ['confirmed', 'processing', 'shipped', 'delivered', 'completed'];
        $storeId = $this->storeId();

        return OrderItem::whereIn('order_id', function ($query) use ($paidStatuses, $storeId) {
            $query->select('id')->from('orders')->whereIn('status', $paidStatuses)->whereNull('deleted_at');

            if ($storeId) {
                $query->where('store_id', $storeId);
            }
        })
            ->selectRaw('product_id, SUM(quantity) as total_qty, SUM(total) as total_revenue')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->with('product')
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->product?->name ?? '—',
                'slug' => $row->product?->slug,
                'total_qty' => (int) $row->total_qty,
                'total_revenue' => (float) $row->total_revenue,
            ])->toArray();
    }

    public function lowStockProducts(int $limit = 5): array
    {
        $storeId = $this->storeId();
        $query = Inventory::with(['product', 'productVariant'])
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', 5);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query
            ->orderBy('quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'product_id' => $inv->product_id,
                'variant_id' => $inv->product_variant_id,
                'product_name' => $inv->product?->name ?? '—',
                'variant_name' => $inv->productVariant?->name,
                'quantity' => $inv->quantity,
                'reserved' => $inv->reserved_quantity,
                'available' => $inv->getAvailableQuantity(),
            ])->toArray();
    }

    public function recentOrders(int $limit = 5): array
    {
        $storeId = $this->storeId();
        $query = Order::with(['user'])->latest();

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'total' => (float) $o->total,
                'shipping_name' => $o->shipping_name,
                'created_at' => $o->created_at?->toIso8601String(),
            ])->toArray();
    }

    public function clearCache(?int $storeId = null): void
    {
        $storeId ??= $this->storeId();

        if ($storeId) {
            Cache::forget("dashboard.stats.store:{$storeId}:v1");
        }

        Cache::forget('dashboard.stats.v1');
    }

    private function storeId(): ?int
    {
        try {
            return app(AdminStoreContext::class)->selectedId()
                ?? app(CurrentStore::class)->scopeId();
        } catch (\Throwable) {
            return null;
        }
    }
}
