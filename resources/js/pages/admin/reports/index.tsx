import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatPrice } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';

interface Props {
    filters: { from: string; to: string };
    totals: { orders: number; revenue: number; vat: number; discounts: number; shipping: number };
    salesDaily: { date: string; orders: number; revenue: string; vat: string; discounts: string; shipping: string }[];
    byPayment: { method: string; orders: number; revenue: string }[];
    byCoupon: { coupon_code: string; orders: number; discount_given: string; revenue: string }[];
    byShipping: { shipping_method_name: string | null; orders: number; shipping_revenue: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Reports', href: '/admin/reports' },
];

export default function ReportsIndex({ filters, totals, salesDaily, byPayment, byCoupon, byShipping }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const apply = () => router.get('/admin/reports', { from, to }, { preserveState: true });
    const exportUrl = (type: string) => `/admin/reports/export?type=${type}&from=${from}&to=${to}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <div className="flex flex-col gap-6 p-4">
                <Heading title="Reports" description="Sales, VAT, coupons, shipping and payments: filter by date and export CSV for accounting." />

                <div className="flex flex-wrap items-end gap-3 rounded-xl border p-4">
                    <div className="grid gap-1">
                        <Label htmlFor="from">From</Label>
                        <Input id="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="to">To</Label>
                        <Input id="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                    </div>
                    <Button onClick={apply}>Apply</Button>
                    <span className="text-muted-foreground text-xs">
                        Showing {filters.from} → {filters.to}
                    </span>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <div className="bg-card rounded-xl border p-4">
                        <p className="text-muted-foreground text-sm">Orders</p>
                        <p className="text-2xl font-bold">{totals.orders}</p>
                    </div>
                    <div className="bg-card rounded-xl border p-4">
                        <p className="text-muted-foreground text-sm">Revenue</p>
                        <p className="text-2xl font-bold">{formatPrice(totals.revenue)}</p>
                    </div>
                    <div className="bg-card rounded-xl border p-4">
                        <p className="text-muted-foreground text-sm">VAT collected</p>
                        <p className="text-2xl font-bold">{formatPrice(totals.vat)}</p>
                    </div>
                    <div className="bg-card rounded-xl border p-4">
                        <p className="text-muted-foreground text-sm">Discounts given</p>
                        <p className="text-2xl font-bold">{formatPrice(totals.discounts)}</p>
                    </div>
                </div>

                <div className="bg-card rounded-xl border p-5">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold">Daily sales</h2>
                        <a href={exportUrl('sales')} className="hover:bg-accent inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm">
                            <Download className="h-4 w-4" /> Sales CSV
                        </a>
                    </div>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted">
                                <tr>
                                    <th className="px-3 py-2 text-left">Date</th>
                                    <th className="px-3 py-2 text-right">Orders</th>
                                    <th className="px-3 py-2 text-right">Revenue</th>
                                    <th className="px-3 py-2 text-right">VAT</th>
                                    <th className="px-3 py-2 text-right">Discounts</th>
                                    <th className="px-3 py-2 text-right">Shipping</th>
                                </tr>
                            </thead>
                            <tbody>
                                {salesDaily.map((r) => (
                                    <tr key={r.date} className="border-t">
                                        <td className="px-3 py-2">{r.date}</td>
                                        <td className="px-3 py-2 text-right">{r.orders}</td>
                                        <td className="px-3 py-2 text-right">{formatPrice(Number(r.revenue))}</td>
                                        <td className="px-3 py-2 text-right">{formatPrice(Number(r.vat))}</td>
                                        <td className="px-3 py-2 text-right">{formatPrice(Number(r.discounts))}</td>
                                        <td className="px-3 py-2 text-right">{formatPrice(Number(r.shipping))}</td>
                                    </tr>
                                ))}
                                {salesDaily.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground px-3 py-8 text-center">
                                            No sales in range.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="bg-card rounded-xl border p-5">
                        <div className="flex items-center justify-between">
                            <h3 className="font-semibold">By payment</h3>
                            <a href={exportUrl('payments')} className="text-primary inline-flex items-center gap-1 text-xs hover:underline">
                                <Download className="h-3 w-3" /> CSV
                            </a>
                        </div>
                        <div className="mt-3 space-y-2">
                            {byPayment.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">-</p>
                            ) : (
                                byPayment.map((r) => (
                                    <div key={r.method} className="flex justify-between text-sm">
                                        <span>{r.method}</span>
                                        <span>
                                            {r.orders} · {formatPrice(Number(r.revenue))}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                    <div className="bg-card rounded-xl border p-5">
                        <div className="flex items-center justify-between">
                            <h3 className="font-semibold">By shipping</h3>
                            <a href={exportUrl('shipping')} className="text-primary inline-flex items-center gap-1 text-xs hover:underline">
                                <Download className="h-3 w-3" /> CSV
                            </a>
                        </div>
                        <div className="mt-3 space-y-2">
                            {byShipping.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">-</p>
                            ) : (
                                byShipping.map((r, i) => (
                                    <div key={i} className="flex justify-between text-sm">
                                        <span>{r.shipping_method_name ?? '-'}</span>
                                        <span>
                                            {r.orders} · {formatPrice(Number(r.shipping_revenue))}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                    <div className="bg-card rounded-xl border p-5">
                        <div className="flex items-center justify-between">
                            <h3 className="font-semibold">Coupons</h3>
                            <a href={exportUrl('coupons')} className="text-primary inline-flex items-center gap-1 text-xs hover:underline">
                                <Download className="h-3 w-3" /> CSV
                            </a>
                        </div>
                        <div className="mt-3 space-y-2">
                            {byCoupon.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No coupon sales.</p>
                            ) : (
                                byCoupon.map((r) => (
                                    <div key={r.coupon_code} className="flex justify-between text-sm">
                                        <span className="font-mono">{r.coupon_code}</span>
                                        <span>
                                            {r.orders} · -{formatPrice(Number(r.discount_given))}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border bg-amber-50 p-4 text-sm dark:bg-amber-950/20">
                    <p className="font-semibold">VAT export</p>
                    <p className="text-muted-foreground">ZATCA-ready line-item VAT by order, for your accountant.</p>
                    <a
                        href={exportUrl('vat')}
                        className="bg-primary text-primary-foreground mt-2 inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm"
                    >
                        <Download className="h-4 w-4" /> Export VAT CSV
                    </a>
                </div>
            </div>
        </AppLayout>
    );
}
