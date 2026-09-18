import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatPrice } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Customer {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    created_at: string;
    orders_count: number;
    pending_orders_count: number;
    total_spent: string | null;
}

interface Paginated {
    data: Customer[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    customers: Paginated;
    filters: { search: string; sort: string; direction: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Customers', href: '/admin/customers' },
];

export default function CustomersIndex({ customers, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    const applySearch = () => router.get('/admin/customers', { search, sort: filters.sort, direction: filters.direction }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customers" />
            <div className="flex flex-col gap-4 p-4">
                <Heading title="Customers" description={`${customers.total} customers · total spent reflects paid orders`} />

                <div className="flex gap-2">
                    <Input
                        placeholder="Search name / email / phone"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && applySearch()}
                        className="max-w-sm"
                    />
                    <Button onClick={applySearch}>Search</Button>
                    {filters.search && (
                        <Button variant="outline" onClick={() => router.get('/admin/customers', {})}>
                            Clear
                        </Button>
                    )}
                </div>

                <div className="bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted">
                            <tr>
                                <th className="px-4 py-3 text-left">Customer</th>
                                <th className="px-4 py-3 text-left">Contact</th>
                                <th className="px-4 py-3 text-right">Orders</th>
                                <th className="px-4 py-3 text-right">Spent</th>
                                <th className="px-4 py-3 text-right">Pending</th>
                                <th className="px-4 py-3 text-right">Joined</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {customers.data.map((c) => (
                                <tr key={c.id} className="hover:bg-accent/30 border-t">
                                    <td className="px-4 py-3 font-medium">{c.name}</td>
                                    <td className="px-4 py-3">
                                        <div>{c.email}</div>
                                        {c.phone && <div className="text-muted-foreground text-xs">{c.phone}</div>}
                                    </td>
                                    <td className="px-4 py-3 text-right">{c.orders_count}</td>
                                    <td className="px-4 py-3 text-right font-semibold">{formatPrice(Number(c.total_spent ?? 0))}</td>
                                    <td className="px-4 py-3 text-right">{c.pending_orders_count}</td>
                                    <td className="text-muted-foreground px-4 py-3 text-right text-xs">
                                        {new Date(c.created_at).toLocaleDateString()}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/admin/customers/${c.id}`} className="text-primary text-sm hover:underline">
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {customers.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-muted-foreground px-4 py-12 text-center">
                                        No customers found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {customers.last_page > 1 && (
                    <div className="flex gap-2">
                        {Array.from({ length: customers.last_page }, (_, i) => i + 1).map((page) => (
                            <Button
                                key={page}
                                variant={page === customers.current_page ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => router.get('/admin/customers', { ...filters, page })}
                            >
                                {page}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
