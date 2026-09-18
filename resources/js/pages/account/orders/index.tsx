import Pagination from '@/components/store/pagination';
import StoreLayout from '@/layouts/store-layout';
import { apiStore, getUser } from '@/lib/auth';
import { formatPrice } from '@/lib/format';
import { useT } from '@/lib/store';
import type { AccountOrder, PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
    confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    processing: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    shipped: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
    delivered: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    completed: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    expired: 'bg-gray-200 text-gray-600 dark:bg-neutral-700 dark:text-neutral-300',
};

export default function AccountOrdersIndex() {
    const t = useT();
    const [orders, setOrders] = useState<PaginatedData<AccountOrder> | null>(null);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);

    useEffect(() => {
        if (!getUser()) {
            router.visit('/account/login?redirect=/account/orders');
            return;
        }

        setLoading(true);
        void apiStore<PaginatedData<AccountOrder>>(`/orders?page=${page}`)
            .then((res) => {
                if (res.ok && res.data) {
                    setOrders(res.data);
                } else {
                    setOrders(null);
                    if (!getUser()) {
                        router.visit('/account/login?redirect=/account/orders');
                    }
                }
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [page]);

    return (
        <StoreLayout title={t('store.my_orders')}>
            <div className="mx-auto max-w-3xl px-4 py-8">
                <nav className="mb-4 text-sm text-[var(--store-muted)]">
                    <Link href="/account" className="hover:underline">
                        {t('store.account')}
                    </Link>
                    <span className="mx-1">/</span>
                    <span className="text-[var(--store-text)]">{t('store.my_orders')}</span>
                </nav>

                <h1 className="mb-6 text-2xl font-bold">{t('store.order_history')}</h1>

                {loading ? (
                    <div className="space-y-3" aria-label={t('store.loading')}>
                        {[0, 1, 2].map((i) => (
                            <div key={i} className="animate-pulse rounded-lg border border-[var(--store-border)] p-4">
                                <div className="h-4 w-1/3 rounded bg-gray-200 dark:bg-neutral-700" />
                                <div className="mt-2 h-4 w-1/4 rounded bg-gray-200 dark:bg-neutral-700" />
                            </div>
                        ))}
                    </div>
                ) : !orders || orders.data.length === 0 ? (
                    <div className="py-12 text-center">
                        <p className="mb-4 text-[var(--store-muted)]">{t('store.no_orders')}</p>
                        <Link href="/products" className="rounded-md bg-[var(--store-accent)] px-4 py-2 text-sm text-white hover:opacity-90">
                            {t('store.products')}
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {orders.data.map((order) => (
                            <Link
                                key={order.id}
                                href={`/account/orders/${order.id}`}
                                className="block rounded-lg border border-[var(--store-border)] p-4 transition hover:shadow-md"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="font-mono text-sm font-semibold">{order.order_number}</span>
                                    <span
                                        className={`rounded-lg px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[order.status] ?? STATUS_STYLES.expired}`}
                                    >
                                        {order.status}
                                    </span>
                                </div>
                                <div className="mt-2 flex items-center justify-between text-sm">
                                    <span className="text-[var(--store-muted)]">
                                        {new Date(order.created_at).toLocaleDateString()} · {order.items?.length ?? 0} items
                                    </span>
                                    <span className="font-bold">{formatPrice(order.total)}</span>
                                </div>
                            </Link>
                        ))}
                        <Pagination
                            data={orders}
                            onPageChange={(url) => {
                                const next = new URL(url, window.location.origin).searchParams.get('page');
                                setPage(next ? Number(next) : 1);
                            }}
                        />
                    </div>
                )}
            </div>
        </StoreLayout>
    );
}
