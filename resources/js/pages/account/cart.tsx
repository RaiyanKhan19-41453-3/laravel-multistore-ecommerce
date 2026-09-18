import ProductImage from '@/components/store/product-image';
import QuantityStepper from '@/components/store/quantity-stepper';
import StoreLayout from '@/layouts/store-layout';
import { apiStore } from '@/lib/auth';
import { formatPrice } from '@/lib/format';
import { useT } from '@/lib/store';
import type { CartDiscount, CartSummary, ItemDiscount } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, Check, ShoppingBag, Tag, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

function levelTag(d: CartDiscount | ItemDiscount): string {
    switch (d.level) {
        case 'product':
            return d.target ?? 'product';
        case 'category':
            return d.target ? `category: ${d.target}` : 'category';
        case 'brand':
            return d.target ? `brand: ${d.target}` : 'brand';
        default:
            return 'sitewide';
    }
}

export default function StoreCart() {
    const t = useT();
    const [cart, setCart] = useState<CartSummary | null>(null);
    const [loading, setLoading] = useState(true);
    const [coupon, setCoupon] = useState('');
    const [couponBusy, setCouponBusy] = useState(false);
    const [couponError, setCouponError] = useState<string | null>(null);

    const fetchCart = () => {
        void apiStore<CartSummary>('/cart').then((res) => {
            if (res.ok && res.data) {
                setCart(res.data);
            }
            setLoading(false);
        });
    };

    useEffect(() => {
        fetchCart();
    }, []);

    const updateQuantity = (itemId: number, newQty: number) => {
        if (newQty < 1) return;

        void apiStore<CartSummary>(`/cart/items/${itemId}`, {
            method: 'PATCH',
            body: { quantity: newQty },
        }).then((res) => {
            if (res.ok && res.data) setCart(res.data);
        });
    };

    const removeItem = (itemId: number) => {
        void apiStore<CartSummary>(`/cart/items/${itemId}`, {
            method: 'DELETE',
        }).then((res) => {
            if (res.ok && res.data) setCart(res.data);
        });
    };

    const applyCoupon = () => {
        if (!coupon.trim()) return;

        setCouponBusy(true);
        setCouponError(null);

        void apiStore<CartSummary>('/cart/coupon', {
            body: { code: coupon.trim() },
        }).then((res) => {
            setCouponBusy(false);

            if (res.ok && res.data) {
                setCart(res.data);
                setCoupon('');
            } else {
                setCouponError(res.message ?? 'Invalid coupon code.');
            }
        });
    };

    const removeCoupon = () => {
        void apiStore<CartSummary>('/cart/coupon', {
            method: 'DELETE',
        }).then((res) => {
            if (res.ok && res.data) setCart(res.data);
        });
    };

    if (loading) {
        return (
            <StoreLayout title="Cart">
                <div className="mx-auto max-w-4xl px-4 py-12 text-[var(--store-muted)]">Loading cart...</div>
            </StoreLayout>
        );
    }

    if (!cart || cart.items.length === 0) {
        return (
            <StoreLayout title={t('store.cart')}>
                <div className="mx-auto max-w-2xl px-4 py-20 text-center">
                    <span className="mx-auto flex h-20 w-20 items-center justify-center rounded-lg bg-[var(--store-card-hover)] text-[var(--store-muted)]">
                        <ShoppingBag className="h-9 w-9" />
                    </span>
                    <h1 className="mt-6 text-2xl font-bold tracking-tight">{t('store.cart_empty_title')}</h1>
                    <p className="mt-2 text-[var(--store-muted)]">{t('store.cart_empty_text')}</p>
                    <Link
                        href="/products"
                        className="mt-7 inline-flex items-center gap-2 rounded-lg bg-[var(--store-accent)] px-7 py-3 text-sm font-bold text-[var(--store-accent-ink)] transition hover:-translate-y-0.5 hover:opacity-90"
                    >
                        {t('store.continue_shopping')}
                        <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    const discounts = cart.discount_details ? (Array.isArray(cart.discount_details) ? cart.discount_details : [cart.discount_details]) : [];

    return (
        <StoreLayout title={t('store.cart')}>
            <div className="store-container py-8">
                <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                    {t('store.shopping_cart')} ({cart.item_count})
                </h1>

                {/* Steps */}
                <ol className="mt-5 flex items-center gap-2 text-xs font-semibold">
                    <li className="flex items-center gap-1.5 rounded-lg bg-[var(--store-text)] px-3.5 py-1.5 text-[var(--store-bg)]">
                        <span className="flex h-4 w-4 items-center justify-center rounded-lg bg-[var(--store-bg)] text-[10px] text-[var(--store-text)]">1</span>
                        {t('store.steps_cart')}
                    </li>
                    <li className="h-px w-8 bg-[var(--store-border)]" />
                    <li className="flex items-center gap-1.5 rounded-lg border border-[var(--store-border)] px-3.5 py-1.5 text-[var(--store-muted)]">
                        <span className="flex h-4 w-4 items-center justify-center rounded-lg bg-[var(--store-card-hover)] text-[10px]">2</span>
                        {t('store.steps_details')}
                    </li>
                    <li className="h-px w-8 bg-[var(--store-border)]" />
                    <li className="flex items-center gap-1.5 rounded-lg border border-[var(--store-border)] px-3.5 py-1.5 text-[var(--store-muted)]">
                        <span className="flex h-4 w-4 items-center justify-center rounded-lg bg-[var(--store-card-hover)] text-[10px]">3</span>
                        {t('store.steps_done')}
                    </li>
                </ol>

                <div className="mt-6 grid items-start gap-6 lg:grid-cols-3">
                    {/* Items */}
                    <div className="lg:col-span-2">
                        <div className="divide-y divide-[var(--store-border)] rounded-lg border border-[var(--store-border)] bg-[var(--store-card)]">
                            {cart.items.map((item) => (
                                <div key={item.id} className="p-4 sm:p-5">
                                    <div className="flex gap-4">
                                        {/* Image */}
                                        <Link href={`/products/${item.product.slug}`} className="h-24 w-24 flex-shrink-0 overflow-hidden rounded-xl border border-[var(--store-border)] bg-[var(--store-card-hover)]">
                                            <ProductImage src={item.image} seed={item.product.id} alt={item.product.name} />
                                        </Link>

                                        {/* Info */}
                                        <div className="flex flex-1 flex-col justify-between gap-3">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <Link href={`/products/${item.product.slug}`} className="text-sm font-semibold transition hover:text-[var(--store-accent)]">
                                                        {item.product.name}
                                                    </Link>
                                                    {item.product_variant && (
                                                        <p className="mt-0.5 inline-block rounded-lg bg-[var(--store-card-hover)] px-2.5 py-0.5 text-xs text-[var(--store-muted)]">
                                                            {item.product_variant.name}
                                                        </p>
                                                    )}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => removeItem(item.id)}
                                                    aria-label="Remove item"
                                                    className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-[var(--store-muted)] transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>

                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <QuantityStepper small value={item.quantity} min={1} onChange={(qty) => updateQuantity(item.id, qty)} />

                                                <div className="text-end">
                                                    <p className="text-base font-bold">{formatPrice(item.line_total)}</p>
                                                    {item.quantity > 1 && (
                                                        <p className="text-xs text-[var(--store-muted)]">{formatPrice(item.unit_price)} each</p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Per-item discounts */}
                                    {item.item_discounts.length > 0 && (
                                        <div className="mt-2.5 ms-28 space-y-1">
                                            {item.item_discounts.map((d, i) => (
                                                <div key={i} className="flex items-center gap-1.5 text-xs font-medium text-[var(--store-success)]">
                                                    <Check className="h-3 w-3 flex-shrink-0" />
                                                    <span>
                                                        {d.name}
                                                        <span className="ms-1 opacity-70">({levelTag(d)})</span>
                                                        <span className="ms-1 font-bold">-{formatPrice(d.amount)}</span>
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>

                        <Link href="/products" className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--store-accent)] hover:underline">
                            {t('store.continue_shopping')}
                            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                        </Link>
                    </div>

                    {/* Summary */}
                    <div className="lg:col-span-1">
                        <div className="rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5 lg:sticky lg:top-24">
                            <h2 className="text-lg font-bold">{t('store.order_summary')}</h2>

                            <div className="mt-4 space-y-3 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-[var(--store-muted)]">Subtotal</span>
                                    <span className="font-semibold">{formatPrice(cart.subtotal)}</span>
                                </div>

                                {/* Discounts breakdown */}
                                {discounts.length > 0 && (
                                    <div className="space-y-1.5 rounded-xl bg-[var(--store-success-soft)] p-3">
                                        <p className="text-[11px] font-bold tracking-wider text-[var(--store-success)] uppercase">Discounts applied</p>
                                        {discounts.map((d, i) => (
                                            <div key={i} className="flex items-center justify-between text-[var(--store-success)]">
                                                <span className="flex items-center gap-1.5">
                                                    <Check className="h-3 w-3 flex-shrink-0" />
                                                    <span className="truncate">
                                                        {d.name}
                                                        <span className="ms-1 text-xs opacity-70">({levelTag(d)})</span>
                                                    </span>
                                                </span>
                                                <span className="flex-shrink-0 font-bold">-{formatPrice(d.amount)}</span>
                                            </div>
                                        ))}
                                        <div className="flex justify-between border-t border-[var(--store-success)]/20 pt-1.5 font-bold text-[var(--store-success)]">
                                            <span>You save</span>
                                            <span>-{formatPrice(cart.discount_total)}</span>
                                        </div>
                                    </div>
                                )}

                                <div className="flex justify-between border-t border-[var(--store-border)] pt-3 text-base font-bold">
                                    <span>Total</span>
                                    <span>{formatPrice(cart.total)}</span>
                                </div>
                            </div>

                            {/* Coupon */}
                            <div className="mt-4 border-t border-[var(--store-border)] pt-4">
                                {cart.coupon_code ? (
                                    <div className="rounded-xl bg-[var(--store-success-soft)] px-3.5 py-2.5">
                                        <div className="flex items-center justify-between">
                                            <span className="flex items-center gap-1.5 text-sm font-bold text-[var(--store-success)]">
                                                <Tag className="h-3.5 w-3.5" />
                                                {cart.coupon_code}
                                            </span>
                                            <button type="button" onClick={removeCoupon} className="text-xs font-medium text-red-500 hover:underline">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <p className="mb-2 text-xs font-medium text-[var(--store-muted)]">Have a coupon?</p>
                                        <div className="flex gap-2">
                                            <input
                                                type="text"
                                                value={coupon}
                                                onChange={(e) => setCoupon(e.target.value)}
                                                placeholder="Enter code"
                                                className="min-w-0 flex-1 rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3 py-2 text-sm outline-none transition uppercase placeholder:normal-case focus:border-[var(--store-accent)]"
                                            />
                                            <button
                                                type="button"
                                                onClick={applyCoupon}
                                                disabled={couponBusy || !coupon.trim()}
                                                className="rounded-xl border border-[var(--store-border)] px-4 py-2 text-sm font-semibold transition hover:bg-[var(--store-card-hover)] disabled:opacity-50"
                                            >
                                                {couponBusy ? '...' : 'Apply'}
                                            </button>
                                        </div>
                                        {couponError && <p className="mt-1.5 text-xs text-red-500">{couponError}</p>}
                                    </>
                                )}
                            </div>

                            <Link
                                href="/checkout"
                                className="mt-5 flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--store-accent)] py-3 text-center text-sm font-bold text-[var(--store-accent-ink)] transition hover:-translate-y-0.5 hover:opacity-90"
                            >
                                {t('store.proceed_checkout')}
                                <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </StoreLayout>
    );
}
