import StoreLayout from '@/layouts/store-layout';
import { apiStore, getUser } from '@/lib/auth';
import { formatPrice } from '@/lib/format';
import { useT } from '@/lib/store';
import type { AccountOrder } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Package, Tag, Truck } from 'lucide-react';
import { useEffect, useState } from 'react';

const CANCELLABLE = ['pending', 'confirmed', 'processing'];

function StatusTimeline({ order }: { order: AccountOrder }) {
    const steps = [
        { key: 'placed', label: 'Placed', at: order.created_at },
        { key: 'paid', label: 'Paid', at: order.paid_at },
        { key: 'shipped', label: 'Shipped', at: order.shipped_at },
        { key: 'delivered', label: 'Delivered', at: order.delivered_at },
    ];

    if (order.status === 'cancelled') {
        return (
            <div className="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                Cancelled{order.cancellation_reason ? `: ${order.cancellation_reason}` : '.'}
            </div>
        );
    }

    return (
        <ol className="space-y-3">
            {steps.map((step) => (
                <li key={step.key} className="flex items-center gap-3 text-sm">
                    <span
                        className={`flex h-6 w-6 items-center justify-center rounded-lg text-xs font-bold ${step.at ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500 dark:bg-neutral-700'}`}
                    >
                        {step.at ? '✓' : '·'}
                    </span>
                    <span className={step.at ? 'font-medium' : 'text-[var(--store-muted)]'}>{step.label}</span>
                    {step.at && <span className="text-xs text-[var(--store-muted)]">{new Date(step.at).toLocaleString()}</span>}
                </li>
            ))}
        </ol>
    );
}

export default function AccountOrderShow({ orderId }: { orderId: number }) {
    const t = useT();
    const [order, setOrder] = useState<AccountOrder | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [reason, setReason] = useState('');
    const [cancelling, setCancelling] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);

    const load = () => {
        setLoading(true);
        setError(null);
        void apiStore<AccountOrder>(`/orders/${orderId}`)
            .then((res) => {
                if (res.ok && res.data) {
                    setOrder(res.data);
                } else {
                    // Token may have expired mid-session (apiStore clears it on 401).
                    if (!getUser()) {
                        router.visit(`/account/login?redirect=/account/orders/${orderId}`);
                        return;
                    }
                    setError(res.message ?? t('store.error_loading'));
                }
            })
            .catch(() => setError(t('store.error_loading')))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        if (!getUser()) {
            router.visit(`/account/login?redirect=/account/orders/${orderId}`);
            return;
        }
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [orderId]);

    const cancel = () => {
        if (!order || !confirm(t('store.cancel_order') + '?')) return;
        setCancelling(true);
        setNotice(null);
        void apiStore(`/orders/${order.id}/cancel`, {
            body: { reason: reason.trim() || null },
        })
            .then((res) => {
                setCancelling(false);
                if (res.ok) {
                    setNotice(t('store.order_cancelled'));
                    load();
                } else {
                    setNotice(res.message ?? t('store.error_loading'));
                }
            })
            .catch(() => {
                setCancelling(false);
                setNotice(t('store.error_loading'));
            });
    };

    if (loading) {
        return (
            <StoreLayout title={t('store.order_details')}>
                <div className="mx-auto max-w-2xl px-4 py-12">
                    <div className="animate-pulse space-y-3">
                        <div className="h-6 w-1/2 rounded bg-gray-200 dark:bg-neutral-700" />
                        <div className="h-32 rounded bg-gray-200 dark:bg-neutral-700" />
                        <div className="h-24 rounded bg-gray-200 dark:bg-neutral-700" />
                    </div>
                </div>
            </StoreLayout>
        );
    }

    if (error || !order) {
        return (
            <StoreLayout title={t('store.order_details')}>
                <div className="mx-auto max-w-2xl px-4 py-12 text-center">
                    <p className="mb-4 text-[var(--store-muted)]">{error ?? t('store.page_not_found')}</p>
                    <Link href="/account/orders" className="text-sm text-[var(--store-accent)] hover:underline">
                        ← {t('store.my_orders')}
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    const canCancel = CANCELLABLE.includes(order.status);

    return (
        <StoreLayout title={`${t('store.order_details')} ${order.order_number}`}>
            <div className="mx-auto max-w-2xl px-4 py-8">
                <nav className="mb-4 text-sm text-[var(--store-muted)]">
                    <Link href="/account" className="hover:underline">
                        {t('store.account')}
                    </Link>
                    <span className="mx-1">/</span>
                    <Link href="/account/orders" className="hover:underline">
                        {t('store.my_orders')}
                    </Link>
                    <span className="mx-1">/</span>
                    <span className="font-mono text-[var(--store-text)]">{order.order_number}</span>
                </nav>

                <div className="mb-4 flex items-center justify-between">
                    <h1 className="font-mono text-xl font-bold">{order.order_number}</h1>
                    <span className="rounded-lg bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                        {order.status}
                    </span>
                </div>

                <div className="mb-4 rounded-lg border border-[var(--store-border)] p-6">
                    <h2 className="mb-3 text-lg font-semibold">{t('store.status')}</h2>
                    <StatusTimeline order={order} />
                </div>

                <div className="mb-4 rounded-lg border border-[var(--store-border)] p-6">
                    <div className="mb-4 flex items-center gap-2">
                        <Package className="h-5 w-5 text-[var(--store-muted)]" />
                        <h2 className="text-lg font-semibold">Items ({order.items.length})</h2>
                    </div>
                    <div className="divide-y divide-[var(--store-border)]">
                        {order.items.map((item) => (
                            <div key={item.id} className="flex items-start justify-between py-3 first:pt-0 last:pb-0">
                                <div className="flex-1">
                                    {item.product ? (
                                        <Link href={`/products/${item.product.slug}`} className="font-medium hover:underline">
                                            {item.name}
                                        </Link>
                                    ) : (
                                        <p className="font-medium">{item.name}</p>
                                    )}
                                    <p className="text-sm text-[var(--store-muted)]">
                                        {formatPrice(item.unit_price)} × {item.quantity}
                                    </p>
                                </div>
                                <p className="font-medium">{formatPrice(item.total)}</p>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 space-y-2 border-t border-[var(--store-border)] pt-4 text-sm">
                        <div className="flex justify-between">
                            <span className="text-[var(--store-muted)]">Subtotal</span>
                            <span>{formatPrice(order.subtotal)}</span>
                        </div>
                        {order.discount_total > 0 && (
                            <div className="flex justify-between text-green-600">
                                <span>Discount{order.coupon_code ? ` (${order.coupon_code})` : ''}</span>
                                <span>-{formatPrice(order.discount_total)}</span>
                            </div>
                        )}
                        <div className="flex justify-between">
                            <span className="text-[var(--store-muted)]">Shipping</span>
                            <span>{order.shipping_cost > 0 ? formatPrice(order.shipping_cost) : 'Free'}</span>
                        </div>
                        {order.tax_amount > 0 && (
                            <div className="flex justify-between">
                                <span className="text-[var(--store-muted)]">Tax</span>
                                <span>{formatPrice(order.tax_amount)}</span>
                            </div>
                        )}
                        <div className="flex justify-between border-t border-[var(--store-border)] pt-2 text-base font-bold">
                            <span>Total</span>
                            <span>{formatPrice(order.total)}</span>
                        </div>
                    </div>
                </div>

                {order.shipments.length > 0 && (
                    <div className="mb-4 rounded-lg border border-[var(--store-border)] p-6">
                        <div className="mb-3 flex items-center gap-2">
                            <Truck className="h-5 w-5 text-[var(--store-muted)]" />
                            <h2 className="text-lg font-semibold">{t('store.tracking')}</h2>
                        </div>
                        <div className="space-y-3">
                            {order.shipments.map((shipment) => (
                                <div key={shipment.id} className="rounded-md bg-blue-50 p-4 text-sm dark:bg-blue-900/20">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="font-medium text-blue-800 dark:text-blue-300">{shipment.courier ?? '-'}</p>
                                            {shipment.tracking_number && (
                                                <p className="text-blue-700 dark:text-blue-400">
                                                    Tracking: <span className="font-mono">{shipment.tracking_number}</span>
                                                </p>
                                            )}
                                        </div>
                                        <span className="rounded-lg bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800 dark:bg-blue-800 dark:text-blue-200">
                                            {shipment.status.replace('_', ' ')}
                                        </span>
                                    </div>
                                    {shipment.note && <p className="mt-2 text-xs text-blue-600 italic dark:text-blue-300">{shipment.note}</p>}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="mb-4 rounded-lg border border-[var(--store-border)] p-6">
                    <div className="mb-2 flex items-center gap-2">
                        <Tag className="h-5 w-5 text-[var(--store-muted)]" />
                        <h2 className="text-lg font-semibold">Shipping To</h2>
                    </div>
                    <div className="space-y-1 text-sm">
                        <p>
                            {order.shipping_name} · {order.shipping_phone}
                        </p>
                        <p className="text-[var(--store-muted)]">
                            {order.shipping_address}, {order.shipping_city}
                            {order.shipping_state ? `, ${order.shipping_state}` : ''}, {order.shipping_country}
                        </p>
                    </div>
                </div>

                {canCancel && (
                    <div className="mb-4 rounded-lg border border-[var(--store-border)] p-6">
                        <h2 className="mb-3 text-lg font-semibold">{t('store.cancel_order')}</h2>
                        <label className="mb-1 block text-sm font-medium">{t('store.cancel_reason')}</label>
                        <input
                            type="text"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            maxLength={255}
                            className="mb-3 w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                        />
                        <button
                            type="button"
                            onClick={cancel}
                            disabled={cancelling}
                            className="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
                        >
                            {cancelling ? '...' : t('store.cancel_order')}
                        </button>
                        {notice && <p className="mt-2 text-sm text-[var(--store-muted)]">{notice}</p>}
                    </div>
                )}
            </div>
        </StoreLayout>
    );
}
