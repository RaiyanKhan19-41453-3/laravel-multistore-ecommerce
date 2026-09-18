<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function index(Request $request): Response
    {
        $query = Inventory::with(['product', 'productVariant']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                    ->orWhereHas('productVariant', fn ($vq) => $vq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            if ($filter === 'in_stock') {
                $query->where('quantity', '>', 0);
            } elseif ($filter === 'low_stock') {
                $query->where('quantity', '>', 0)->where('quantity', '<=', 5);
            } elseif ($filter === 'out_of_stock') {
                $query->where('quantity', '<=', 0);
            }
        }

        $sortField = $request->input('sort', 'product_id');
        $sortDirection = $request->input('direction', 'asc');

        $allowedSorts = ['product_id', 'quantity', 'reserved_quantity'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $inventories = $query->get()->map(fn ($inv) => [
            'id' => $inv->id,
            'product_id' => $inv->product_id,
            'product_variant_id' => $inv->product_variant_id,
            'product_name' => $inv->product->name,
            'variant_name' => $inv->productVariant?->name,
            'variant_sku' => $inv->productVariant?->sku,
            'product_sku' => $inv->product->sku,
            'quantity' => $inv->quantity,
            'reserved_quantity' => $inv->reserved_quantity,
            'available_quantity' => $inv->quantity - $inv->reserved_quantity,
            'is_in_stock' => $inv->quantity > 0,
            'is_low_stock' => $inv->quantity > 0 && $inv->quantity <= 5,
        ]);

        $stats = [
            'total_products' => Inventory::count(),
            'total_stock' => Inventory::sum('quantity'),
            'low_stock' => Inventory::where('quantity', '>', 0)->where('quantity', '<=', 5)->count(),
            'out_of_stock' => Inventory::where('quantity', '<=', 0)->count(),
        ];

        return Inertia::render('admin/inventory/index', [
            'inventories' => $inventories,
            'stats' => $stats,
        ]);
    }

    public function adjust(Request $request, Inventory $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:purchase,adjustment,return,damage,sale',
            'quantity' => 'required|integer|not_in:0',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $this->inventoryService->adjust(
                $inventory,
                $validated['type'],
                $validated['quantity'],
                $validated['note'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('success', 'Stock adjusted successfully.');
    }

    public function movements(Inventory $inventory): JsonResponse
    {
        $movements = $inventory->movements()
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'quantity' => $m->quantity,
                'note' => $m->note,
                'user_name' => $m->user?->name,
                'created_at' => $m->created_at->format('M d, Y H:i'),
            ]);

        return response()->json([
            'movements' => $movements,
        ]);
    }
}
