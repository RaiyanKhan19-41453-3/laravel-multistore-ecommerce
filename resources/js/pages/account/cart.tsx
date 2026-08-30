import StoreLayout from '@/layouts/store-layout';
import { apiStore } from '@/lib/auth';
import type { CartDiscount, CartSummary, ItemDiscount } from '@/types';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Check, Minus, Plus, Tag, Trash2 } from 'lucide-react';

function formatPrice(value: number): string {
    return `৳${Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

function discountRate(d: CartDiscount | ItemDiscount): string {
    return d.type === 'percentage' ? `${d.value ?? d.amount}%` : formatPrice(d.amount);
}

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
            <StoreLayout title="Cart">
                <div className="mx-auto max-w-4xl px-4 py-12 text-center">
                    <h1 className="mb-4 text-2xl font-bold">Your cart is empty</h1>
                    <p className="mb-6 text-[var(--store-muted)]">Looks like you haven&apos;t added anything yet.</p>
                    <Link
                        href="/"
                        className="inline-block rounded-lg bg-[var(--store-accent)] px-6 py-2.5 text-sm font-semibold text-white hover:opacity-90"
                    >
                        Continue shopping
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    const discounts = cart.discount_details
        ? Array.isArray(cart.discount_details)
            ? cart.discount_details
            : [cart.discount_details]
        : [];

    return (
        <StoreLayout title="Cart">
            <div className="mx-auto max-w-4xl px-4 py-8">
                <h1 className="mb-6 text-2xl font-bold">Shopping Cart ({cart.item_count})</h1>

                <div className="grid gap-8 lg:grid-cols-3">
                    {/* Items */}
                    <div className="lg:col-span-2">
                        <div className="divide-y divide-[var(--store-border)] rounded-lg border border-[var(--store-border)]">
                            {cart.items.map((item) => (
                                <div key={item.id} className="p-4">
                                    <div className="flex gap-4">
                                        {/* Image */}
                                        <div className="h-20 w-20 flex-shrink-0 overflow-hidden rounded-md border border-[var(--store-border)] bg-gray-100">
                                            {item.image ? (
                                                <img src={item.image} alt={item.product.name} className="h-full w-full object-cover" />
                                            ) : (
                                                <div className="flex h-full w-full items-center justify-center text-xs text-[var(--store-muted)]">
                                                    No image
                                                </div>
                                            )}
                                        </div>

                                        {/* Info */}
                                        <div className="flex flex-1 flex-col justify-between">
                                            <div>
                                                <Link
                                                    href={`/products/${item.product.slug}`}
                                                    className="text-sm font-medium hover:underline"
                                                >
                                                    {item.product.name}
                                                </Link>
                                                {item.product_variant && (
                                                    <p className="text-xs text-[var(--store-muted)]">{item.product_variant.name}</p>
                                                )}
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => updateQuantity(item.id, item.quantity - 1)}
                                                        disabled={item.quantity <= 1}
                                                        className="flex h-7 w-7 items-center justify-center rounded border border-[var(--store-border)] hover:bg-[var(--store-card-hover)] disabled:opacity-40"
                                                    >
                                                        <Minus className="h-3 w-3" />
                                                    </button>
                                                    <span className="w-8 text-center text-sm">{item.quantity}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => updateQuantity(item.id, item.quantity + 1)}
                                                        className="flex h-7 w-7 items-center justify-center rounded border border-[var(--store-border)] hover:bg-[var(--store-card-hover)]"
                                                    >
                                                        <Plus className="h-3 w-3" />
                                                    </button>
                                                </div>

                                                <div className="flex items-center gap-3">
                                                    <div className="text-right">
                                                        <p className="text-sm font-semibold">{formatPrice(item.line_total)}</p>
                                                        {item.quantity > 1 && (
                                                            <p className="text-xs text-[var(--store-muted)]">
                                                                {formatPrice(item.unit_price)} each
                                                            </p>
                                                        )}
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeItem(item.id)}
                                                        className="text-[var(--store-muted)] hover:text-red-500"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Per-item discounts */}
                                    {item.item_discounts.length > 0 && (
                                        <div className="ml-24 mt-2 space-y-1">
                                            {item.item_discounts.map((d, i) => (
                                                <div key={i} className="flex items-center gap-1.5 text-xs text-green-600">
                                                    <Check className="h-3 w-3 flex-shrink-0" />
                                                    <span>
                                                        {d.name}
                                                        <span className="ml-1 opacity-70">
                                                            ({levelTag(d)})
                                                        </span>
                                                        <span className="ml-1 font-medium">-{formatPrice(d.amount)}</span>
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Summary */}
                    <div className="lg:col-span-1">
                        <div className="rounded-lg border border-[var(--store-border)] p-5">
                            <h2 className="mb-4 text-lg font-semibold">Order Summary</h2>

                            <div className="space-y-3 text-sm">
                                {/* Items breakdown */}
                                <div className="space-y-1.5">
                                    {cart.items.map((item) => {
                                        const itemDiscountTotal = item.item_discounts.reduce((sum, d) => sum + d.amount, 0);

                                        return (
                                            <div key={item.id}>
                                                <div className="flex justify-between">
                                                    <span className="truncate text-[var(--store-muted)]">
                                                        {item.product.name}
                                                        {item.quantity > 1 ? ` x${item.quantity}` : ''}
                                                    </span>
                                                    <span>{formatPrice(item.line_total)}</span>
                                                </div>
                                                {itemDiscountTotal > 0 && (
                                                    <div className="pl-3 text-xs text-green-600">
                                                        saves {formatPrice(itemDiscountTotal)}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>

                                <div className="border-t border-[var(--store-border)] pt-2">
                                    <div className="flex justify-between">
                                        <span className="text-[var(--store-muted)]">Subtotal</span>
                                        <span>{formatPrice(cart.subtotal)}</span>
                                    </div>
                                </div>

                                {/* Discounts breakdown */}
                                {discounts.length > 0 && (
                                    <div className="space-y-1.5 border-t border-[var(--store-border)] pt-2">
                                        <p className="text-xs font-medium uppercase tracking-wide text-[var(--store-muted)]">
                                            Discounts applied
                                        </p>
                                        {discounts.map((d, i) => (
                                            <div key={i} className="flex items-center justify-between text-green-600">
                                                <span className="flex items-center gap-1.5">
                                                    <Check className="h-3 w-3 flex-shrink-0" />
                                                    <span className="truncate">
                                                        {d.name}
                                                        <span className="ml-1 text-xs opacity-70">
                                                            ({levelTag(d)})
                                                        </span>
                                                    </span>
                                                </span>
                                                <span className="flex-shrink-0 font-medium">-{formatPrice(d.amount)}</span>
                                            </div>
                                        ))}
                                        <div className="flex justify-between border-t border-[var(--store-border)] pt-1.5 font-medium text-green-600">
                                            <span>You save</span>
                                            <span>-{formatPrice(cart.discount_total)}</span>
                                        </div>
                                    </div>
                                )}

                                <div className="border-t border-[var(--store-border)] pt-2">
                                    <div className="flex justify-between text-base font-bold">
                                        <span>Total</span>
                                        <span>{formatPrice(cart.total)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Coupon */}
                            <div className="mt-5 border-t border-[var(--store-border)] pt-4">
                                {cart.coupon_code ? (
                                    <div className="rounded-md bg-green-50 px-3 py-2 dark:bg-green-900/20">
                                        <div className="flex items-center justify-between">
                                            <span className="flex items-center gap-1.5 text-sm font-medium text-green-700 dark:text-green-400">
                                                <Tag className="h-3.5 w-3.5" />
                                                {cart.coupon_code}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={removeCoupon}
                                                className="text-xs text-red-500 hover:underline"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <p className="mb-2 text-xs text-[var(--store-muted)]">Have a coupon?</p>
                                        <div className="flex gap-2">
                                            <input
                                                type="text"
                                                value={coupon}
                                                onChange={(e) => setCoupon(e.target.value)}
                                                placeholder="Enter code"
                                                className="flex-1 rounded-md border border-[var(--store-border)] px-3 py-1.5 text-sm"
                                            />
                                            <button
                                                type="button"
                                                onClick={applyCoupon}
                                                disabled={couponBusy || !coupon.trim()}
                                                className="rounded-md border border-[var(--store-border)] px-3 py-1.5 text-sm font-medium hover:bg-[var(--store-card-hover)] disabled:opacity-50"
                                            >
                                                {couponBusy ? '...' : 'Apply'}
                                            </button>
                                        </div>
                                        {couponError && <p className="mt-1 text-xs text-red-500">{couponError}</p>}
                                    </>
                                )}
                            </div>

                            <Link
                                href="/checkout"
                                className="mt-5 block w-full rounded-lg bg-[var(--store-accent)] py-2.5 text-center text-sm font-semibold text-white hover:opacity-90"
                            >
                                Proceed to Checkout
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </StoreLayout>
    );
}
