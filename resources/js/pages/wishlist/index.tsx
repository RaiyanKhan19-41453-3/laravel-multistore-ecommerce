import StoreLayout from '@/layouts/store-layout';
import ProductImage from '@/components/store/product-image';
import { apiStore, getUser } from '@/lib/auth';
import { formatPrice } from '@/lib/format';
import { useT } from '@/lib/store';
import type { PaginatedData, WishlistItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function WishlistIndex() {
    const t = useT();
    const [items, setItems] = useState<PaginatedData<WishlistItem> | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!getUser()) {
            router.visit('/account/login?redirect=/wishlist');
            return;
        }

        void apiStore<PaginatedData<WishlistItem>>('/wishlist')
            .then((res) => {
                if (res.ok && res.data) setItems(res.data);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const removeItem = (id: number) => {
        if (!confirm(`${t('store.delete')}?`)) return;

        void apiStore(`/wishlist/${id}`, { method: 'DELETE' }).then((res) => {
            if (res.ok) {
                setItems((prev) => {
                    if (!prev) return prev;
                    return {
                        ...prev,
                        data: prev.data.filter((item) => item.id !== id),
                        total: prev.total - 1,
                    };
                });
            }
        });
    };

    return (
        <StoreLayout title={t('store.wishlist')}>
            <Head>
                <title>{t('store.wishlist')}</title>
            </Head>

            <div className="mx-auto max-w-6xl px-4 py-8">
                <h1 className="mb-6 text-2xl font-bold">{t('store.wishlist')}</h1>

                {loading ? (
                    <div className="space-y-4" aria-label={t('store.loading')}>
                        {[0, 1, 2].map((i) => (
                            <div key={i} className="flex animate-pulse items-center gap-4 rounded-lg border border-[var(--store-border)] p-4">
                                <div className="h-20 w-20 rounded bg-[var(--store-card-hover)]" />
                                <div className="flex-1 space-y-2">
                                    <div className="h-4 w-1/3 rounded bg-[var(--store-card-hover)]" />
                                    <div className="h-4 w-1/5 rounded bg-[var(--store-card-hover)]" />
                                </div>
                            </div>
                        ))}
                    </div>
                ) : !items || items.data.length === 0 ? (
                    <div className="py-12 text-center">
                        <p className="mb-4 text-[var(--store-muted)]">{t('store.wishlist_empty')}</p>
                        <Link href="/products" className="rounded-md bg-[var(--store-accent)] px-4 py-2 text-sm text-white hover:opacity-90">
                            {t('store.products')}
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {items.data.map(
                            (item) =>
                                item.product && (
                                    <div key={item.id} className="flex items-center gap-4 rounded-lg border border-[var(--store-border)] p-4">
                                        <Link href={`/products/${item.product.slug}`} className="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-[var(--store-card-hover)]">
                                            <ProductImage src={item.product.primary_image} seed={item.product.id} alt={item.product.name} />
                                        </Link>
                                        <div className="flex-1">
                                            {item.product.brand && <p className="text-xs text-[var(--store-muted)]">{item.product.brand.name}</p>}
                                            <Link href={`/products/${item.product.slug}`} className="font-medium hover:underline">
                                                {item.product.name}
                                            </Link>
                                            <p className="text-sm font-bold">{formatPrice(item.product.price)}</p>
                                        </div>
                                        <button type="button" onClick={() => removeItem(item.id)} className="text-sm text-red-500 hover:underline">
                                            {t('store.delete')}
                                        </button>
                                    </div>
                                ),
                        )}
                    </div>
                )}
            </div>
        </StoreLayout>
    );
}
