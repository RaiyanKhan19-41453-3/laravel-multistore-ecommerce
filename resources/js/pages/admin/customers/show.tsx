import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { formatPrice } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface OrderRow {
    id: number;
    order_number: string;
    status: string;
    total: string;
    created_at: string;
    items: { name: string; quantity: number }[];
}

interface Address {
    id: number;
    name: string;
    phone: string;
    address: string;
    city: string;
    is_default: boolean;
}

interface Props {
    customer: { id: number; name: string; email: string; phone: string | null; created_at: string; orders_count: number; total_spent: string | null };
    orders: OrderRow[];
    addresses: Address[];
}

export default function CustomerShow({ customer, orders, addresses }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin/dashboard' },
        { title: 'Customers', href: '/admin/customers' },
        { title: customer.name, href: `/admin/customers/${customer.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={customer.name} />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title={customer.name}
                    description={`${customer.email} · ${customer.phone ?? '—'} · ${customer.orders_count} orders · spent ${formatPrice(Number(customer.total_spent ?? 0))}`}
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="bg-card rounded-xl border p-5">
                        <h2 className="font-semibold">Recent orders</h2>
                        <div className="mt-4 space-y-2">
                            {orders.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No orders.</p>
                            ) : (
                                orders.map((o) => (
                                    <Link
                                        key={o.id}
                                        href={`/admin/orders/${o.id}`}
                                        className="hover:bg-accent flex items-center justify-between rounded-lg border px-3 py-2"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">{o.order_number}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {o.items.map((i) => `${i.name}×${i.quantity}`).join(', ')}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-semibold">{formatPrice(o.total)}</p>
                                            <Badge variant="outline">{o.status}</Badge>
                                        </div>
                                    </Link>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="bg-card rounded-xl border p-5">
                        <h2 className="font-semibold">Addresses</h2>
                        <div className="mt-4 space-y-2">
                            {addresses.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No saved addresses.</p>
                            ) : (
                                addresses.map((a) => (
                                    <div key={a.id} className="rounded-lg border p-3">
                                        <p className="text-sm font-medium">
                                            {a.name} {a.is_default && <Badge className="ml-2">Default</Badge>}
                                        </p>
                                        <p className="text-sm">
                                            {a.address}, {a.city}
                                        </p>
                                        <p className="text-muted-foreground text-xs">{a.phone}</p>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
