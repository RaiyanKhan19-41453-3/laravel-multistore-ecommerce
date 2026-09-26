import { apiStore } from '@/lib/auth';
import { formatPrice } from '@/lib/format';
import { useT } from '@/lib/store';
import type { ProductSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { Star, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import Price from './price';
import ProductImage from './product-image';
import QuantityStepper from './quantity-stepper';
import StoreButton from './store-button';

interface QuickVariant {
    id: number;
    name: string;
    price: number;
    image: string | null;
    inventory?: { available: number } | null;
}

interface QuickImage {
    id: number;
    url: string;
}

interface QuickDetail {
    id: number;
    name: string;
    slug: string;
    price: number;
    compare_at_price: number | null;
    primary_image: string | null;
    short_description: string | null;
    type: string;
    brand?: { name: string } | null;
    review_summary?: { total: number; average: number } | null;
    inventory?: { available: number } | null;
    images: QuickImage[];
    variants: QuickVariant[];
}

export const QUICK_VIEW_OPEN_EVENT = 'quickview:open';

export function openQuickView(product: ProductSummary) {
    window.dispatchEvent(new CustomEvent(QUICK_VIEW_OPEN_EVENT, { detail: product }));
}

/**
 * Quick view modal: image, price, variant picker, quantity, add to cart.
 * Enough to buy without opening the details page.
 */
export default function QuickViewModal() {
    const t = useT();
    const [summary, setSummary] = useState<ProductSummary | null>(null);
    const [detail, setDetail] = useState<QuickDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [variantId, setVariantId] = useState<number | null>(null);
    const [quantity, setQuantity] = useState(1);
    const [adding, setAdding] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const [activeImage, setActiveImage] = useState<string | null>(null);

    const close = useCallback(() => {
        setSummary(null);
        setDetail(null);
        setVariantId(null);
        setQuantity(1);
        setNotice(null);
        setActiveImage(null);
    }, []);

    useEffect(() => {
        const onOpen = (e: Event) => {
            const product = (e as CustomEvent<ProductSummary>).detail;
            setSummary(product);
            setDetail(null);
            setVariantId(null);
            setQuantity(1);
            setNotice(null);
            setLoading(true);

            void fetch(`/api/products/${product.slug}`)
                .then((r) => (r.ok ? r.json() : null))
                .then((json: { success: boolean; data: QuickDetail } | null) => {
                    if (json?.success) setDetail(json.data);
                })
                .catch(() => {})
                .finally(() => setLoading(false));
        };

        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') close();
        };

        window.addEventListener(QUICK_VIEW_OPEN_EVENT, onOpen);
        window.addEventListener('keydown', onKey);
        return () => {
            window.removeEventListener(QUICK_VIEW_OPEN_EVENT, onOpen);
            window.removeEventListener('keydown', onKey);
        };
    }, [close]);

    useEffect(() => {
        document.body.style.overflow = summary ? 'hidden' : '';
        return () => {
            document.body.style.overflow = '';
        };
    }, [summary]);

    if (!summary) return null;

    const variants = detail?.variants ?? [];
    const selected = variants.find((v) => v.id === variantId) ?? null;
    const isVariable = (detail?.type ?? summary.type) === 'variable';
    const stock = isVariable ? (selected?.inventory?.available ?? 0) : (detail?.inventory?.available ?? 0);
    const unitPrice = isVariable ? (selected?.price ?? detail?.price ?? summary.price) : (detail?.price ?? summary.price);

    // Gallery: selected variant's own photo first, then the product shots.
    const gallery = selected?.image
        ? [{ id: `v-${selected.id}`, url: selected.image }, ...(detail?.images ?? [])]
        : (detail?.images ?? []);
    const mainImage = activeImage ?? gallery[0]?.url ?? detail?.primary_image ?? summary.primary_image;

    const addToCart = () => {
        const id = detail?.id ?? summary.id;
        if (isVariable && !selected) {
            setNotice(t('store.select_option'));
            return;
        }
        setAdding(true);
        setNotice(null);

        void apiStore('/cart/items', {
            body: { product_id: id, product_variant_id: selected?.id, quantity },
        })
            .then((res) => {
                setAdding(false);
                if (res.ok) {
                    window.dispatchEvent(new CustomEvent('cart:updated'));
                    close();
                    window.dispatchEvent(new CustomEvent('cart:open'));
                } else {
                    setNotice(res.message ?? t('store.error_loading'));
                }
            })
            .catch(() => {
                setAdding(false);
                setNotice(t('store.error_loading'));
            });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-label={summary.name}>
            <button type="button" aria-label={t('store.close')} onClick={close} className="absolute inset-0 cursor-default bg-black/50 backdrop-blur-[2px]" />
            <div className="storefront relative max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-t-2xl bg-[var(--store-card)] text-[var(--store-text)] shadow-2xl sm:rounded-2xl">
                <button
                    type="button"
                    onClick={close}
                    aria-label={t('store.close')}
                    className="absolute top-3 end-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-[var(--store-card)] text-[var(--store-muted)] shadow transition hover:text-[var(--store-text)]"
                >
                    <X className="h-4 w-4" />
                </button>

                {loading || !detail ? (
                    <div className="grid gap-6 p-6 sm:grid-cols-2">
                        <div className="aspect-square animate-pulse rounded-xl bg-[var(--store-card-hover)]" />
                        <div className="space-y-3 py-2">
                            <div className="h-6 w-3/4 animate-pulse rounded bg-[var(--store-card-hover)]" />
                            <div className="h-4 w-1/3 animate-pulse rounded bg-[var(--store-card-hover)]" />
                            <div className="h-10 w-full animate-pulse rounded-full bg-[var(--store-card-hover)]" />
                        </div>
                    </div>
                ) : (
                    <div className="grid sm:grid-cols-2">
                        <div className="flex flex-col gap-2">
                            <div className="relative aspect-square overflow-hidden bg-[var(--store-card-hover)] sm:rounded-s-2xl">
                                <ProductImage src={mainImage} seed={detail.id} alt={detail.name} />
                            </div>
                            {gallery.length > 1 && (
                                <div className="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                    {gallery.map((img) => (
                                        <button
                                            key={img.id}
                                            type="button"
                                            onClick={() => setActiveImage(img.url)}
                                            className={`h-14 w-14 shrink-0 overflow-hidden rounded-lg border-2 transition ${
                                                mainImage === img.url
                                                    ? 'border-[var(--store-accent)]'
                                                    : 'border-transparent opacity-70 hover:opacity-100'
                                            }`}
                                        >
                                            <img src={img.url} alt="" className="h-full w-full object-cover" />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="flex flex-col p-5 sm:p-6">
                            {detail.brand && <p className="text-[11px] font-bold tracking-[0.14em] text-[var(--store-accent)] uppercase">{detail.brand.name}</p>}
                            <h2 className="mt-1 line-clamp-2 text-lg leading-snug font-bold">{detail.name}</h2>
                            {detail.review_summary && detail.review_summary.total > 0 && (
                                <p className="mt-1.5 flex items-center gap-1.5 text-xs text-[var(--store-muted)]">
                                    <Star className="h-3.5 w-3.5 fill-[var(--store-star)] text-[var(--store-star)]" />
                                    <span className="font-bold text-[var(--store-text)]">{detail.review_summary.average.toFixed(1)}</span>(
                                    {detail.review_summary.total})
                                </p>
                            )}
                            <div className="mt-3">
                                <Price value={unitPrice} compareAt={detail.compare_at_price} size="lg" accent />
                            </div>
                            <p className={`mt-2 text-xs font-semibold ${stock > 0 ? 'text-[var(--store-success)]' : 'text-red-500'}`}>
                                {stock > 0 ? `${stock} ${t('store.in_stock')}` : t('store.out_of_stock')}
                            </p>

                            {isVariable && variants.length > 0 && (
                                <label className="mt-4 block text-sm font-semibold">
                                    {t('store.select_option')}
                                    <select
                                        value={variantId ?? ''}
                                        onChange={(e) => {
                                            setVariantId(e.target.value ? Number(e.target.value) : null);
                                            setActiveImage(null);
                                        }}
                                        className="mt-1.5 w-full rounded-lg border border-[var(--store-border)] bg-[var(--store-input)] px-3 py-2.5 text-sm font-normal outline-none focus:border-[var(--store-accent)]"
                                    >
                                        <option value="">{t('store.select_option')}</option>
                                        {variants.map((v) => (
                                            <option key={v.id} value={v.id}>
                                                {v.name} - {formatPrice(v.price)}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}

                            <div className="mt-4 flex items-center gap-3">
                                <QuantityStepper value={quantity} min={1} max={Math.max(stock, 1)} onChange={setQuantity} />
                                <StoreButton onClick={addToCart} disabled={adding || stock <= 0} className="flex-1">
                                    {adding ? '...' : t('store.add_to_cart')}
                                </StoreButton>
                            </div>

                            {notice && <p className="mt-2.5 text-sm font-medium text-red-500">{notice}</p>}

                            <Link
                                href={`/products/${detail.slug}`}
                                className="mt-4 text-center text-sm font-bold text-[var(--store-accent)] hover:underline"
                            >
                                {t('store.view_details')}
                            </Link>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
