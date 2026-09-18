import AppLayout from '@/layouts/app-layout';
import { formatPrice } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Activity, AlertTriangle, ArrowUpRight, BadgeCheck, Clock3, DollarSign, Package, ShoppingCart, TrendingUp, Users } from 'lucide-react';

interface Stats {
    totalRevenue: number;
    todayRevenue: number;
    weekRevenue: number;
    monthRevenue: number;
    totalOrders: number;
    pendingOrders: number;
    cancelledOrders: number;
    aov: number;
    lowStockCount: number;
    outOfStockCount: number;
    totalProducts: number;
    activeProducts: number;
    totalCustomers: number;
    zatcaPending: number;
    zatcaFailed: number;
}

interface ChartRow {
    date: string;
    label: string;
    orders: number;
    revenue: number;
}

interface TopProduct {
    product_id: number;
    name: string;
    slug: string | null;
    total_qty: number;
    total_revenue: number;
}

interface LowStockRow {
    id: number;
    product_id: number;
    variant_id: number | null;
    product_name: string;
    variant_name: string | null;
    quantity: number;
    reserved: number;
    available: number;
}

interface RecentOrder {
    id: number;
    order_number: string;
    status: string;
    total: number;
    shipping_name: string;
    created_at: string | null;
}

interface DashboardProps {
    stats: Stats;
    chart: ChartRow[];
    topProducts: TopProduct[];
    lowStock: LowStockRow[];
    recentOrders: RecentOrder[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/admin/dashboard' }];

function StatCard({
    title,
    value,
    sub,
    icon: Icon,
    accent,
}: {
    title: string;
    value: string;
    sub?: string;
    icon: typeof DollarSign;
    accent?: string;
}) {
    return (
        <div className="bg-card rounded-xl border p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <p className="text-muted-foreground text-sm font-medium">{title}</p>
                <div className={`flex h-8 w-8 items-center justify-center rounded-lg ${accent ?? 'bg-muted'}`}>
                    <Icon className="h-4 w-4" />
                </div>
            </div>
            <p className="mt-2 text-2xl font-bold">{value}</p>
            {sub && <p className="text-muted-foreground mt-1 text-xs">{sub}</p>}
        </div>
    );
}

export default function Dashboard({ stats, chart, topProducts, lowStock, recentOrders }: DashboardProps) {
    const maxRevenue = Math.max(...chart.map((c) => c.revenue), 1);
    const maxOrders = Math.max(...chart.map((c) => c.orders), 1);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
                        <p className="text-muted-foreground text-sm">Real-time store performance — revenue, orders, inventory & compliance.</p>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href="/admin/orders"
                            className="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium hover:opacity-90"
                        >
                            View orders <ArrowUpRight className="h-4 w-4" />
                        </Link>
                        <Link
                            href="/admin/reports"
                            className="hover:bg-accent inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
                        >
                            Reports
                        </Link>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Total revenue"
                        value={formatPrice(stats.totalRevenue)}
                        sub={`${stats.totalOrders} orders · AOV ${formatPrice(stats.aov)}`}
                        icon={DollarSign}
                        accent="bg-green-100 text-green-700 dark:bg-green-900/30"
                    />
                    <StatCard
                        title="This month"
                        value={formatPrice(stats.monthRevenue)}
                        sub={`Week ${formatPrice(stats.weekRevenue)} · Today ${formatPrice(stats.todayRevenue)}`}
                        icon={TrendingUp}
                        accent="bg-blue-100 text-blue-700 dark:bg-blue-900/30"
                    />
                    <StatCard
                        title="Orders"
                        value={`${stats.totalOrders}`}
                        sub={`${stats.pendingOrders} pending · ${stats.cancelledOrders} cancelled`}
                        icon={ShoppingCart}
                        accent="bg-amber-100 text-amber-700 dark:bg-amber-900/30"
                    />
                    <StatCard
                        title="Customers"
                        value={`${stats.totalCustomers}`}
                        sub={`${stats.totalProducts} products (${stats.activeProducts} active)`}
                        icon={Users}
                        accent="bg-violet-100 text-violet-700 dark:bg-violet-900/30"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="bg-card rounded-xl border p-5 shadow-sm lg:col-span-2">
                        <div className="flex items-center justify-between">
                            <h2 className="flex items-center gap-2 font-semibold">
                                <Activity className="h-4 w-4" /> Revenue — last 14 days
                            </h2>
                            <span className="text-muted-foreground text-xs">Hover bars for values</span>
                        </div>
                        <div className="mt-6 flex h-48 items-end gap-1">
                            {chart.map((row) => (
                                <div key={row.date} className="flex flex-1 flex-col items-center gap-1">
                                    <div
                                        title={`${row.label}: ${formatPrice(row.revenue)} · ${row.orders} orders`}
                                        className="bg-primary/90 hover:bg-primary w-full rounded-t transition-all"
                                        style={{ height: `${Math.max(4, (row.revenue / maxRevenue) * 160)}px` }}
                                    />
                                    <span className="text-muted-foreground hidden text-[10px] md:block">{row.label.slice(0, 6)}</span>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 flex h-16 items-end gap-1">
                            {chart.map((row) => (
                                <div key={`o-${row.date}`} className="flex flex-1 flex-col items-center gap-1">
                                    <div
                                        title={`${row.orders} orders`}
                                        className="w-full rounded-t bg-amber-500/80"
                                        style={{ height: `${Math.max(2, (row.orders / maxOrders) * 40)}px` }}
                                    />
                                </div>
                            ))}
                        </div>
                        <p className="text-muted-foreground mt-2 text-center text-xs">
                            Revenue (top) · Orders (bottom) · Max revenue {formatPrice(maxRevenue)}
                        </p>
                    </div>

                    <div className="bg-card rounded-xl border p-5 shadow-sm">
                        <h2 className="flex items-center gap-2 font-semibold">
                            <BadgeCheck className="h-4 w-4" /> Health
                        </h2>
                        <div className="mt-4 space-y-3">
                            <div className="bg-muted flex items-center justify-between rounded-lg p-3">
                                <span className="flex items-center gap-2 text-sm">
                                    <AlertTriangle className="h-4 w-4 text-amber-600" /> Low stock
                                </span>
                                <span className="font-bold">{stats.lowStockCount}</span>
                            </div>
                            <div className="bg-muted flex items-center justify-between rounded-lg p-3">
                                <span className="flex items-center gap-2 text-sm">
                                    <Package className="h-4 w-4 text-red-600" /> Out of stock
                                </span>
                                <span className="font-bold">{stats.outOfStockCount}</span>
                            </div>
                            <div className="bg-muted flex items-center justify-between rounded-lg p-3">
                                <span className="flex items-center gap-2 text-sm">
                                    <Clock3 className="h-4 w-4 text-blue-600" /> ZATCA pending
                                </span>
                                <span className={`font-bold ${stats.zatcaPending > 0 ? 'text-amber-600' : ''}`}>{stats.zatcaPending}</span>
                            </div>
                            <div className="bg-muted flex items-center justify-between rounded-lg p-3">
                                <span className="flex items-center gap-2 text-sm">
                                    <Activity className="h-4 w-4 text-red-600" /> ZATCA failed
                                </span>
                                <span className={`font-bold ${stats.zatcaFailed > 0 ? 'text-red-600' : ''}`}>{stats.zatcaFailed}</span>
                            </div>
                            <Link href="/admin/inventory" className="text-primary block pt-2 text-center text-sm hover:underline">
                                Manage inventory →
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="bg-card rounded-xl border p-5 shadow-sm">
                        <h2 className="font-semibold">Top products (by qty)</h2>
                        <div className="mt-4 space-y-3">
                            {topProducts.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No sales yet.</p>
                            ) : (
                                topProducts.map((p) => (
                                    <div key={p.product_id} className="flex items-center justify-between">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">{p.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {p.total_qty} sold · {formatPrice(p.total_revenue)}
                                            </p>
                                        </div>
                                        {p.slug && (
                                            <Link href={`/admin/products/${p.product_id}`} className="text-primary text-xs hover:underline">
                                                View
                                            </Link>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="bg-card rounded-xl border p-5 shadow-sm">
                        <h2 className="font-semibold">Low stock alerts</h2>
                        <div className="mt-4 space-y-3">
                            {lowStock.length === 0 ? (
                                <p className="text-muted-foreground flex items-center gap-2 text-sm">
                                    <BadgeCheck className="h-4 w-4 text-green-600" /> All stocked.
                                </p>
                            ) : (
                                lowStock.map((r) => (
                                    <div key={r.id} className="flex items-center justify-between">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {r.product_name}
                                                {r.variant_name ? ` — ${r.variant_name}` : ''}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                Qty {r.quantity} · Reserved {r.reserved} · Avail {r.available}
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                            {r.quantity} left
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="bg-card rounded-xl border p-5 shadow-sm">
                        <h2 className="font-semibold">Recent orders</h2>
                        <div className="mt-4 space-y-3">
                            {recentOrders.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No orders yet.</p>
                            ) : (
                                recentOrders.map((o) => (
                                    <Link
                                        key={o.id}
                                        href={`/admin/orders/${o.id}`}
                                        className="hover:bg-accent flex items-center justify-between rounded-lg border px-3 py-2"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">{o.order_number}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {o.shipping_name} · {o.status}
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold">{formatPrice(o.total)}</span>
                                    </Link>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
