<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StoreActivityController extends Controller
{
    public function __construct(
        protected CartService $cartService,
    ) {}

    public function index(Request $request): Response
    {
        $activeCarts = Cart::with(['items.product', 'items.productVariant', 'user'])
            ->where('status', 'active')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Cart $cart) => [
                'id' => $cart->id,
                'user' => $cart->user ? [
                    'id' => $cart->user->id,
                    'name' => $cart->user->name,
                    'email' => $cart->user->email,
                ] : null,
                'guest_token' => $cart->guest_token ? substr($cart->guest_token, 0, 8).'...' : null,
                'item_count' => $cart->items->sum('quantity'),
                'total_value' => $cart->items->sum(fn ($item) => $item->quantity * ($item->productVariant?->price ?? $item->product->price)),
                'items' => $cart->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product->name,
                    'variant_name' => $item->productVariant?->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->productVariant?->price ?? $item->product->price,
                    'line_total' => $item->quantity * ($item->productVariant?->price ?? $item->product->price),
                ]),
                'created_at' => $cart->created_at->toISOString(),
            ]);

        $totalCarts = Cart::where('status', 'active')->count();
        $guestCarts = Cart::where('status', 'active')->whereNull('user_id')->count();
        $userCarts = Cart::where('status', 'active')->whereNotNull('user_id')->count();
        $totalItems = DB::table('cart_items')
            ->join('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->where('carts.status', 'active')
            ->sum('cart_items.quantity');

        $popularProducts = DB::table('cart_items')
            ->join('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->join('products', 'products.id', '=', 'cart_items.product_id')
            ->where('carts.status', 'active')
            ->select('products.id', 'products.name', 'products.slug', 'products.price', DB::raw('SUM(cart_items.quantity) as total_quantity'), DB::raw('COUNT(DISTINCT carts.id) as cart_count'))
            ->groupBy('products.id', 'products.name', 'products.slug', 'products.price')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        $recentGuests = Cart::where('status', 'active')
            ->whereNull('user_id')
            ->whereNotNull('guest_token')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Cart $cart) => [
                'id' => $cart->id,
                'guest_token' => substr($cart->guest_token, 0, 8).'...',
                'item_count' => $cart->items()->sum('quantity'),
                'created_at' => $cart->created_at->toISOString(),
            ]);

        return Inertia::render('admin/store-activity/index', [
            'activeCarts' => $activeCarts,
            'stats' => [
                'total_carts' => $totalCarts,
                'guest_carts' => $guestCarts,
                'user_carts' => $userCarts,
                'total_items' => $totalItems,
            ],
            'popularProducts' => $popularProducts,
            'recentGuests' => $recentGuests,
        ]);
    }

    public function clearAllCarts(): RedirectResponse
    {
        $carts = Cart::where('status', 'active')->get();

        foreach ($carts as $cart) {
            $this->cartService->clearCart($cart);
            $cart->update(['status' => 'cleared']);
        }

        return to_route('admin.store-activity.index');
    }
}
