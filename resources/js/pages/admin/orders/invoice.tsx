import AppLayout from '@/layouts/app-layout';
import { formatPrice } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Props {
    order: {
        id: number;
        order_number: string;
        status: string;
        created_at: string | null;
        shipping_name: string;
        shipping_phone: string | null;
        shipping_address: string | null;
        shipping_city: string | null;
        shipping_state: string | null;
        shipping_country: string | null;
        shipping_postal_code: string | null;
        shipping_method_name: string | null;
        coupon_code: string | null;
        notes: string | null;
    };
    lines: { name: string; sku: string; quantity: number; unit_price: string; total: string }[];
    totals: { subtotal: number; discount: number; shipping: number; tax: number; grand: number };
    qrSvg: string | null;
    qrPayload: string | null;
    isVat: boolean;
    store: { name: string; currency: string };
    htmlUrl: string;
    pdfUrl: string;
}

export default function InvoicePage({ order, lines, totals, qrSvg, qrPayload, isVat, store, htmlUrl, pdfUrl }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin/dashboard' },
        { title: 'Orders', href: '/admin/orders' },
        { title: order.order_number, href: `/admin/orders/${order.id}` },
        { title: 'Invoice', href: `/admin/orders/${order.id}/invoice` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Invoice ${order.order_number}`} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold">Invoice {order.order_number}</h1>
                        <p className="text-muted-foreground text-sm">
                            {order.status} · {order.created_at ? new Date(order.created_at).toLocaleString() : '—'} · {store.name}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a href={htmlUrl} target="_blank" rel="noreferrer" className="hover:bg-accent rounded-lg border px-4 py-2 text-sm">
                            Open printable
                        </a>
                        <a href={pdfUrl} className="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm hover:opacity-90">
                            Download PDF
                        </a>
                        <Link href={`/admin/orders/${order.id}/packing-slip`} className="hover:bg-accent rounded-lg border px-4 py-2 text-sm">
                            Packing slip
                        </Link>
                    </div>
                </div>

                <div className="bg-card overflow-hidden rounded-xl border">
                    <div className="grid gap-6 p-6 md:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground text-xs tracking-widest uppercase">Bill / Ship to</p>
                            <p className="mt-1 font-semibold">{order.shipping_name}</p>
                            <p className="text-sm">{order.shipping_phone}</p>
                            <p className="text-sm">
                                {order.shipping_address}, {order.shipping_city} {order.shipping_state ? `, ${order.shipping_state}` : ''} ·{' '}
                                {order.shipping_country} {order.shipping_postal_code}
                            </p>
                        </div>
                        <div className="text-sm md:text-right">
                            <p>
                                <span className="text-muted-foreground">Shipping:</span> {order.shipping_method_name ?? '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">Coupon:</span> {order.coupon_code ?? '—'}
                            </p>
                            {order.notes && <p className="text-muted-foreground">Note: {order.notes}</p>}
                        </div>
                    </div>

                    <div className="border-t">
                        <table className="w-full text-sm">
                            <thead className="bg-muted">
                                <tr>
                                    <th className="px-4 py-2 text-left">Item</th>
                                    <th className="px-4 py-2 text-right">Qty</th>
                                    <th className="px-4 py-2 text-right">Unit</th>
                                    <th className="px-4 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((l, i) => (
                                    <tr key={i} className="border-t">
                                        <td className="px-4 py-2">
                                            <div className="font-medium">{l.name}</div>
                                            <div className="text-muted-foreground text-xs">{l.sku}</div>
                                        </td>
                                        <td className="px-4 py-2 text-right">{l.quantity}</td>
                                        <td className="px-4 py-2 text-right">{formatPrice(l.unit_price)}</td>
                                        <td className="px-4 py-2 text-right font-semibold">{formatPrice(l.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t p-6">
                        <div className="ml-auto max-w-sm space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Subtotal</span>
                                <span>{formatPrice(totals.subtotal)}</span>
                            </div>
                            {totals.discount > 0 && (
                                <div className="flex justify-between text-green-600">
                                    <span>Discount</span>
                                    <span>-{formatPrice(totals.discount)}</span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Shipping</span>
                                <span>{formatPrice(totals.shipping)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{isVat ? 'VAT' : 'Tax'}</span>
                                <span>{formatPrice(totals.tax)}</span>
                            </div>
                            <div className="flex justify-between border-t pt-2 text-base font-bold">
                                <span>Grand total</span>
                                <span>{formatPrice(totals.grand)}</span>
                            </div>
                        </div>
                    </div>

                    {qrSvg && (
                        <div className="bg-muted/30 flex gap-4 border-t p-6">
                            <div className="h-[140px] w-[140px] shrink-0 border bg-white p-2" dangerouslySetInnerHTML={{ __html: qrSvg }} />
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-xs tracking-widest uppercase">ZATCA QR (TLV Base64)</p>
                                <p className="mt-1 font-mono text-xs break-all">{qrPayload}</p>
                                <p className="text-muted-foreground mt-1 text-xs">Scan to verify per ZATCA Phase 1.</p>
                            </div>
                        </div>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border bg-white">
                    <div className="text-muted-foreground p-2 text-right text-xs">Preview — use Download PDF / Print for exact paper layout</div>
                    <iframe src={htmlUrl} title="Printable invoice" className="h-[900px] w-full border-0" />
                </div>
            </div>
        </AppLayout>
    );
}
