import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ShoppingCart, Users, Package, UserCheck, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Store Activity', href: '/admin/store-activity' },
];

interface CartItem {
    id: number;
    product_name: string;
    variant_name: string | null;
    quantity: number;
    unit_price: number;
    line_total: number;
}

interface ActiveCart {
    id: number;
    user: { id: number; name: string; email: string } | null;
    guest_token: string | null;
    item_count: number;
    total_value: number;
    items: CartItem[];
    created_at: string;
}

interface Stats {
    total_carts: number;
    guest_carts: number;
    user_carts: number;
    total_items: number;
}

interface PopularProduct {
    id: number;
    name: string;
    slug: string;
    price: number;
    total_quantity: number;
    cart_count: number;
}

interface RecentGuest {
    id: number;
    guest_token: string;
    item_count: number;
    created_at: string;
}

function formatPrice(value: number): string {
    return `৳${Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function StatCard({ icon: Icon, label, value }: { icon: React.ElementType; label: string; value: number }) {
    return (
        <div className="rounded-xl border bg-white p-4 shadow-sm dark:bg-neutral-900">
            <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-neutral-100 dark:bg-neutral-800">
                    <Icon className="h-5 w-5 text-neutral-600 dark:text-neutral-400" />
                </div>
                <div>
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="text-2xl font-bold">{value}</p>
                </div>
            </div>
        </div>
    );
}

export default function StoreActivityIndex({
    activeCarts,
    stats,
    popularProducts,
    recentGuests,
}: {
    activeCarts: ActiveCart[];
    stats: Stats;
    popularProducts: PopularProduct[];
    recentGuests: RecentGuest[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Store Activity" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Store Activity" description="Monitor storefront usage, carts, and popular products" />
                    {stats.total_carts > 0 && (
                        <button
                            type="button"
                            onClick={() => {
                                if (window.confirm('Clear all active carts? This will release all reserved inventory.')) {
                                    router.post(route('admin.store-activity.clear-all-carts'));
                                }
                            }}
                            className="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                        >
                            <Trash2 className="h-4 w-4" />
                            Clear All Carts
                        </button>
                    )}
                </div>

                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard icon={ShoppingCart} label="Active Carts" value={stats.total_carts} />
                    <StatCard icon={UserCheck} label="Logged-in Carts" value={stats.user_carts} />
                    <StatCard icon={Users} label="Guest Carts" value={stats.guest_carts} />
                    <StatCard icon={Package} label="Total Items in Carts" value={stats.total_items} />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Active Carts */}
                    <div className="rounded-xl border bg-white shadow-sm dark:bg-neutral-900">
                        <div className="border-b px-4 py-3">
                            <h3 className="text-sm font-semibold">Active Carts</h3>
                        </div>
                        <div className="max-h-96 overflow-y-auto">
                            {activeCarts.length === 0 ? (
                                <p className="p-4 text-sm text-muted-foreground">No active carts.</p>
                            ) : (
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">User</th>
                                            <th className="px-4 py-2 font-medium">Items</th>
                                            <th className="px-4 py-2 font-medium">Value</th>
                                            <th className="px-4 py-2 font-medium">When</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {activeCarts.map((cart) => (
                                            <tr key={cart.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                                <td className="px-4 py-2">
                                                    {cart.user ? (
                                                        <div>
                                                            <p className="font-medium">{cart.user.name}</p>
                                                            <p className="text-xs text-muted-foreground">{cart.user.email}</p>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">Guest ({cart.guest_token})</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2">{cart.item_count}</td>
                                                <td className="px-4 py-2 font-medium">{formatPrice(cart.total_value)}</td>
                                                <td className="px-4 py-2 text-xs text-muted-foreground">{formatDate(cart.created_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>

                    {/* Popular Products */}
                    <div className="rounded-xl border bg-white shadow-sm dark:bg-neutral-900">
                        <div className="border-b px-4 py-3">
                            <h3 className="text-sm font-semibold">Popular Products in Carts</h3>
                        </div>
                        <div className="max-h-96 overflow-y-auto">
                            {popularProducts.length === 0 ? (
                                <p className="p-4 text-sm text-muted-foreground">No products in any carts.</p>
                            ) : (
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">Product</th>
                                            <th className="px-4 py-2 font-medium">Price</th>
                                            <th className="px-4 py-2 font-medium">Qty in Carts</th>
                                            <th className="px-4 py-2 font-medium">In # Carts</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {popularProducts.map((p) => (
                                            <tr key={p.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                                <td className="px-4 py-2 font-medium">{p.name}</td>
                                                <td className="px-4 py-2">{formatPrice(p.price)}</td>
                                                <td className="px-4 py-2">{p.total_quantity}</td>
                                                <td className="px-4 py-2">{p.cart_count}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>

                {/* Recent Guest Carts */}
                {recentGuests.length > 0 && (
                    <div className="rounded-xl border bg-white shadow-sm dark:bg-neutral-900">
                        <div className="border-b px-4 py-3">
                            <h3 className="text-sm font-semibold">Recent Guest Carts</h3>
                        </div>
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                                <tr>
                                    <th className="px-4 py-2 font-medium">Guest Token</th>
                                    <th className="px-4 py-2 font-medium">Items</th>
                                    <th className="px-4 py-2 font-medium">Created</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentGuests.map((g) => (
                                    <tr key={g.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                        <td className="px-4 py-2 font-mono text-xs">{g.guest_token}</td>
                                        <td className="px-4 py-2">{g.item_count}</td>
                                        <td className="px-4 py-2 text-xs text-muted-foreground">{formatDate(g.created_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
