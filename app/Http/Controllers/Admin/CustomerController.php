<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\AdminStoreContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $adminStores = app(AdminStoreContext::class);
        $storeId = $adminStores->selectedId();

        $ordersScope = fn ($q) => $storeId !== null
            ? $q->where('orders.store_id', $storeId)
            : $q;

        $query = User::query()
            ->when($storeId !== null, fn ($q) => $q->whereHas('orders', fn ($oq) => $oq->where('orders.store_id', $storeId)))
            ->withCount(['orders' => $ordersScope])
            ->withSum(['orders as total_spent' => fn ($q) => $ordersScope($q)->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])], 'total')
            ->withCount(['orders as pending_orders_count' => fn ($q) => $ordersScope($q)->where('status', 'pending')]);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $sort = $request->query('sort', 'created_at');
        $direction = $request->query('direction', 'desc');

        if (in_array($sort, ['created_at', 'name', 'email', 'total_spent', 'orders_count'])) {
            $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $customers = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->query('search', ''),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function show(User $customer): Response
    {
        $storeId = app(AdminStoreContext::class)->selectedId();

        $ordersQuery = Order::where('user_id', $customer->id);

        if ($storeId !== null) {
            $ordersQuery->where('store_id', $storeId);

            if (! $ordersQuery->exists()) {
                abort(404);
            }
        }

        $customer->loadCount(['orders', 'addresses']);
        $customer->loadSum(['orders as total_spent' => fn ($q) => $q->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])], 'total');

        $orders = $ordersQuery
            ->with(['items'])
            ->latest()
            ->limit(10)
            ->get();

        $addresses = [];
        if (method_exists($customer, 'addresses')) {
            $addresses = $customer->addresses()->latest()->get();
        }

        return Inertia::render('admin/customers/show', [
            'customer' => $customer,
            'orders' => $orders,
            'addresses' => $addresses,
        ]);
    }
}
