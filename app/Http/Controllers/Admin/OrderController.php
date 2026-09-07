<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Couriers\CourierService;
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
        protected CourierService $courierService,
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
        $order->load(['items', 'payments', 'user', 'coupon', 'shipments']);

        $couriers = Courier::where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('admin/orders/show', [
            'order' => $order,
            'couriers' => $couriers,
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

    public function storeShipment(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'courier_id' => 'required|exists:couriers,id',
            'tracking_number' => 'required|string|max:255',
            'note' => 'nullable|string|max:500',
        ]);

        $courier = Courier::findOrFail($validated['courier_id']);

        $order->update(['fulfillment_type' => 'courier']);

        $order->shipments()->create([
            'courier_id' => $courier->id,
            'courier' => $courier->name,
            'tracking_number' => $validated['tracking_number'],
            'status' => 'pending',
            'note' => $validated['note'] ?? null,
        ]);

        return to_route('admin.orders.show', $order);
    }

    public function updateShipment(Request $request, Order $order, Shipment $shipment): RedirectResponse
    {
        if ($shipment->order_id !== $order->id) {
            abort(422, 'Shipment does not belong to this order.');
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,picked,in_transit,out_for_delivery,delivered,failed,returned',
            'tracking_number' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:500',
        ]);

        $updateData = ['status' => $validated['status']];

        if (! empty($validated['tracking_number'])) {
            $updateData['tracking_number'] = $validated['tracking_number'];
        }

        if (array_key_exists('note', $validated)) {
            $updateData['note'] = $validated['note'];
        }

        $shipment->update($updateData);

        if ($validated['status'] === 'delivered') {
            $order->update(['status' => 'delivered', 'delivered_at' => now()]);
        } elseif ($validated['status'] === 'in_transit' || $validated['status'] === 'picked') {
            $order->update(['status' => 'shipped', 'shipped_at' => $order->shipped_at ?? now()]);
        }

        return to_route('admin.orders.show', $order);
    }

    public function destroyShipment(Order $order, Shipment $shipment): RedirectResponse
    {
        if ($shipment->order_id !== $order->id) {
            abort(422, 'Shipment does not belong to this order.');
        }

        $shipment->delete();

        return to_route('admin.orders.show', $order);
    }

    public function sendToCourier(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'courier_id' => 'required|exists:couriers,id',
        ]);

        $courier = Courier::findOrFail($validated['courier_id']);

        try {
            $this->courierService->sendToCourier($order, $courier);
        } catch (\Exception $e) {
            return back()->withErrors(['courier_id' => 'Failed to send to courier: '.$e->getMessage()]);
        }

        return to_route('admin.orders.show', $order);
    }
}
