import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye } from 'lucide-react';

interface Order {
    id: number;
    order_number: string;
    status: string;
    total: string;
    shipping_name: string;
    shipping_phone: string;
    created_at: string;
    items: { id: number; name: string; quantity: number }[];
    payments: { method: string; status: string }[];
    user: { id: number; name: string; email: string };
}

interface PaginatedOrders {
    data: Order[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Filters {
    search?: string;
    status?: string;
    sort?: string;
    direction?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Orders', href: '/admin/orders' },
];

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'processing', label: 'Processing' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'expired', label: 'Expired' },
];

function getStatusBadge(status: string) {
    const map: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
        pending: { label: 'Pending', variant: 'outline' },
        confirmed: { label: 'Confirmed', variant: 'default' },
        processing: { label: 'Processing', variant: 'default' },
        shipped: { label: 'Shipped', variant: 'default' },
        delivered: { label: 'Delivered', variant: 'default' },
        completed: { label: 'Completed', variant: 'default' },
        cancelled: { label: 'Cancelled', variant: 'destructive' },
        expired: { label: 'Expired', variant: 'secondary' },
    };
    return map[status] ?? { label: status, variant: 'secondary' as const };
}

function SortableHeader({ label, sortField }: { label: string; sortField: string }) {
    const filters = usePage<{ filters?: Filters }>().props.filters ?? { search: '', status: 'all' };
    const sort = filters.sort;
    const direction = filters.direction ?? 'desc';
    const isActive = sort === sortField;
    const newDirection = isActive && direction === 'asc' ? 'desc' : 'asc';

    return (
        <Link
            href={route('admin.orders.index', {
                ...filters,
                sort: sortField,
                direction: newDirection,
            })}
            className="inline-flex items-center gap-1 hover:underline"
        >
            {label}
            {isActive ? (
                direction === 'asc' ? (
                    <ArrowUp className="h-3 w-3" />
                ) : (
                    <ArrowDown className="h-3 w-3" />
                )
            ) : null}
        </Link>
    );
}

export default function OrdersIndex({ orders }: { orders: PaginatedOrders }) {
    const filters = usePage<{ filters?: Filters }>().props.filters ?? { search: '', status: 'all' };
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? 'all',
    });

    const applyFilters = () => {
        const params: Record<string, string> = {};
        if (data.search) params.search = data.search;
        if (data.status && data.status !== 'all') params.status = data.status;
        if (filters.sort) params.sort = filters.sort;
        if (filters.direction) params.direction = filters.direction;
        get(route('admin.orders.index', params), { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Orders" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Orders" description="Manage customer orders" />
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            applyFilters();
                        }}
                        className="flex flex-1 gap-2"
                    >
                        <Input
                            placeholder="Search by order #, name, or phone..."
                            value={data.search}
                            onChange={(e) => setData('search', e.target.value)}
                            className="max-w-sm"
                        />
                        <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button type="submit" variant="secondary">
                            Filter
                        </Button>
                    </form>
                </div>

                <div className="border-sidebar-border/70 relative overflow-hidden rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    <SortableHeader label="Order" sortField="created_at" />
                                </th>
                                <th className="px-4 py-3 font-medium">Customer</th>
                                <th className="px-4 py-3 font-medium">Items</th>
                                <th className="px-4 py-3 font-medium">Payment</th>
                                <th className="px-4 py-3 font-medium">
                                    <SortableHeader label="Total" sortField="total" />
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    <SortableHeader label="Status" sortField="status" />
                                </th>
                                <th className="px-4 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {orders.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-neutral-500">
                                        No orders found.
                                    </td>
                                </tr>
                            ) : (
                                orders.data.map((order) => {
                                    const status = getStatusBadge(order.status);
                                    return (
                                        <tr key={order.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                            <td className="px-4 py-3">
                                                <div>
                                                    <p className="font-medium">{order.order_number}</p>
                                                    <p className="text-xs text-neutral-500">
                                                        {new Date(order.created_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div>
                                                    <p className="text-sm">{order.shipping_name}</p>
                                                    <p className="text-xs text-neutral-500">{order.shipping_phone}</p>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-neutral-500">{order.items.length}</td>
                                            <td className="px-4 py-3">
                                                {order.payments.length > 0 ? (
                                                    <div>
                                                        <p className="text-sm capitalize">{order.payments[0].method}</p>
                                                        <p className="text-xs text-neutral-500 capitalize">{order.payments[0].status}</p>
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-neutral-400">COD</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-medium">৳{Number(order.total).toLocaleString()}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={status.variant}>{status.label}</Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={route('admin.orders.show', order.id)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {orders.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2">
                        {Array.from({ length: orders.last_page }, (_, i) => i + 1).map((page) => (
                            <Link
                                key={page}
                                href={route('admin.orders.index', { ...filters, page })}
                                className={`inline-flex h-8 w-8 items-center justify-center rounded-md text-sm ${
                                    page === orders.current_page
                                        ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                                        : 'hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                }`}
                            >
                                {page}
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
