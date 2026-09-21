import { apiStore } from '@/lib/auth';
import { formatPrice } from '@/lib/format';
import { useT } from '@/lib/store';
import type { CartSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, ShoppingBag, Trash2, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import ProductImage from './product-image';
import QuantityStepper from './quantity-stepper';
import StoreButton from './store-button';

export const MINI_CART_OPEN_EVENT = 'cart:open';

export function openMiniCart() {
    window.dispatchEvent(new CustomEvent(MINI_CART_OPEN_EVENT));
}

export default function MiniCart() {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [cart, setCart] = useState<CartSummary | null>(null);
    const [loading, setLoading] = useState(false);
    const [busyItem, setBusyItem] = useState<number | null>(null);

    const fetchCart = useCallback(() => {
        setLoading(true);
        void apiStore<CartSummary>('/cart')
            .then((res) => {
                if (res.ok && res.data) setCart(res.data);
            })
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        const handleOpen = () => {
            setOpen(true);
            fetchCart();
        };

        window.addEventListener(MINI_CART_OPEN_EVENT, handleOpen);

        return () => window.removeEventListener(MINI_CART_OPEN_EVENT, handleOpen);
    }, [fetchCart]);

    useEffect(() => {
        if (!open) return;

        document.body.style.overflow = 'hidden';
        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpen(false);
        };
        window.addEventListener('keydown', handleKey);

        return () => {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', handleKey);
        };
    }, [open ]);

    const refresh = (updated: CartSummary | null) => {
        if (updated) setCart(updated);
        window.dispatchEvent(new CustomEvent('cart:updated'));
    };

    const updateQuantity = (itemId: number, quantity: number) => {
        if (quantity < 1) return;
        setBusyItem(itemId);
        void apiStore<CartSummary>(`/cart/items/${itemId}`, { method: 'PATCH', body: { quantity } })
            .then((res) => {
                if (res.ok && res.data) refresh(res.data);
            })
            .finally(() => setBusyItem(null));
    };

    const removeItem = (itemId: number) => {
        setBusyItem(itemId);
        void apiStore<CartSummary>(`/cart/items/${itemId}`, { method: 'DELETE' })
            .then((res) => {
                if (res.ok && res.data) refresh(res.data);
            })
            .finally(() => setBusyItem(null));
    };

    return (
        <div className={`fixed inset-0 z-50 ${open ? '' : 'pointer-events-none'}`} aria-hidden={!open}>
            {/* Overlay */}
            <div
                onClick={() => setOpen(false)}
                className={`absolute inset-0 bg-black/45 backdrop-blur-[2px] transition-opacity duration-300 ${open ? 'opacity-100' : 'opacity-0'}`}
            />
            {/* Panel slides in from the RIGHT */}
            <aside
                role="dialog"
                aria-label={t('store.cart')}
                className={`absolute top-0 bottom-0 right-0 flex w-full max-w-md flex-col bg-[var(--store-bg)] shadow-2xl transition-transform duration-300 ease-out ${
                    open ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                <div className="flex items-center justify-between border-b border-[var(--store-border)] px-5 py-4">
                    <h2 className="flex items-center gap-2 text-base font-bold">
                        <ShoppingBag className="h-5 w-5" />
                        {t('store.cart')}
                        {cart && cart.item_count > 0 && (
                            <span className="rounded-lg bg-[var(--store-card-hover)] px-2.5 py-0.5 text-xs font-bold">{cart.item_count}</span>
                        )}
                    </h2>
                    <button
                        type="button"
                        aria-label="Close cart"
                        onClick={() => setOpen(false)}
                        className="flex h-9 w-9 items-center justify-center rounded-lg transition hover:bg-[var(--store-card-hover)]"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto px-5 py-4">
                    {loading && !cart ? (
                        <div className="space-y-3" aria-label={t('store.loading')}>
                            {[0, 1, 2].map((i) => (
                                <div key={i} className="flex animate-pulse gap-3">
                                    <div className="h-20 w-20 shrink-0 rounded-xl bg-[var(--store-card-hover)]" />
                                    <div className="flex-1 space-y-2 py-1">
                                        <div className="h-4 w-3/4 rounded bg-[var(--store-card-hover)]" />
                                        <div className="h-4 w-1/4 rounded bg-[var(--store-card-hover)]" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : !cart || cart.items.length === 0 ? (
                        <div className="flex h-full flex-col items-center justify-center py-16 text-center">
                            <span className="flex h-16 w-16 items-center justify-center rounded-lg bg-[var(--store-card-hover)] text-[var(--store-muted)]">
                                <ShoppingBag className="h-7 w-7" />
                            </span>
                            <p className="mt-4 font-bold">{t('store.cart_empty_title')}</p>
                            <p className="mt-1 text-sm text-[var(--store-muted)]">{t('store.cart_empty_text')}</p>
                            <StoreButton
                                href="/products"
                                onClick={() => setOpen(false)}
                                size="sm"
                                className="mt-6"
                            >
                                {t('store.continue_shopping')}
                            </StoreButton>
                        </div>
                    ) : (
                        <ul className="divide-y divide-[var(--store-border)]">
                            {cart.items.map((item) => (
                                <li key={item.id} className={`flex gap-3 py-4 ${busyItem === item.id ? 'opacity-60' : ''}`}>
                                    <Link
                                        href={`/products/${item.product.slug}`}
                                        onClick={() => setOpen(false)}
                                        className="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-[var(--store-border)] bg-[var(--store-card-hover)]"
                                    >
                                        <ProductImage src={item.image} seed={item.product.id} alt={item.product.name} />
                                    </Link>
                                    <div className="flex min-w-0 flex-1 flex-col">
                                        <div className="flex items-start justify-between gap-2">
                                            <Link
                                                href={`/products/${item.product.slug}`}
                                                onClick={() => setOpen(false)}
                                                className="truncate text-sm font-semibold transition hover:text-[var(--store-accent)]"
                                            >
                                                {item.product.name}
                                            </Link>
                                            <button
                                                type="button"
                                                aria-label="Remove item"
                                                onClick={() => removeItem(item.id)}
                                                className="text-[var(--store-muted)] transition hover:text-red-500"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                        {item.product_variant && <p className="mt-0.5 text-xs text-[var(--store-muted)]">{item.product_variant.name}</p>}
                                        <div className="mt-auto flex items-center justify-between pt-2">
                                            <QuantityStepper small value={item.quantity} min={1} onChange={(qty) => updateQuantity(item.id, qty)} />
                                            <p className="text-sm font-bold">{formatPrice(item.line_total)}</p>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {cart && cart.items.length > 0 && (
                    <div className="border-t border-[var(--store-border)] bg-[var(--store-card)] px-5 py-4">
                        {cart.discount_total > 0 && (
                            <div className="mb-1.5 flex justify-between text-sm font-medium text-[var(--store-success)]">
                                <span>You save</span>
                                <span>-{formatPrice(cart.discount_total)}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-base font-bold">
                            <span>{t('store.order_summary')}</span>
                            <span>{formatPrice(cart.total)}</span>
                        </div>
                        <div className="mt-3.5 grid grid-cols-2 gap-2">
                            <StoreButton
                                href="/cart"
                                onClick={() => setOpen(false)}
                                size="sm"
                                variant="outline"
                            >
                                {t('store.view_cart')}
                            </StoreButton>
                            <StoreButton
                                href="/checkout"
                                onClick={() => setOpen(false)}
                                size="sm"
                                className="flex-1"
                            >
                                {t('store.checkout')}
                                <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                            </StoreButton>
                        </div>
                    </div>
                )}
            </aside>
        </div>
    );
}
