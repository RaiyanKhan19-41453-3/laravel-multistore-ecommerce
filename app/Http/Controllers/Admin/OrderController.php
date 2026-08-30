<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    public function index(Request $request): Response
    {
        $query = Order::with(['items', 'payments', 'user']);

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('shipping_name', 'like', "%{$search}%")
                    ->orWhere('shipping_phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $allowedSorts = ['created_at', 'total', 'status'];
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $orders = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => [
                'search' => $request->query('search', ''),
                'status' => $request->query('status', ''),
                'sort' => $sort,
                'direction' => $direction === 'asc' ? 'asc' : 'desc',
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['items', 'payments', 'user', 'coupon']);

        return Inertia::render('admin/orders/show', [
            'order' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:confirmed,processing,shipped,delivered,completed,cancelled',
        ]);

        try {
            $this->orderService->updateStatus($order, $validated['status']);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Invalid order status transition', [
                'order_id' => $order->id,
                'from' => $order->status,
                'to' => $validated['status'],
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return to_route('admin.orders.show', $order);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->orderService->cancel($order, $validated['cancellation_reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Failed to cancel order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return to_route('admin.orders.show', $order);
    }
}
