import StoreLayout from '@/layouts/store-layout';
import StoreButton from '@/components/store/store-button';
import { apiStore, getUser, type StoreUser } from '@/lib/auth';
import { formatPrice } from '@/lib/format';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { CheckCircle, Package, Search, Tag, Truck } from 'lucide-react';

interface OrderItem {
    name: string;
    sku: string;
    unit_price: number;
    quantity: number;
    subtotal: number;
    discount_amount: number;
    total: number;
}

interface AppliedDiscount {
    id: number;
    name: string;
    type: string;
    value: number;
}

interface Order {
    id: number;
    order_number: string;
    status: string;
    subtotal: number;
    discount_total: number;
    shipping_cost: number;
    tax_amount: number;
    total: number;
    coupon_code: string | null;
    guest_email: string | null;
    shipping_name: string;
    shipping_phone: string;
    shipping_address: string;
    shipping_city: string;
    shipping_state: string;
    shipping_country: string;
    notes: string | null;
    items: OrderItem[];
    applied_discounts: AppliedDiscount[];
    shipments: Shipment[];
    zatca?: {
        seller_name_ar: string;
        vat_number: string;
        qr_svg: string;
    } | null;
}

interface Shipment {
    id: number;
    courier: string | null;
    tracking_number: string | null;
    status: string;
    note: string | null;
    created_at: string;
}

interface LookupForm {
    email: string;
    phone: string;
    order_number: string;
}

interface PageProps {
    orderNumber?: string;
    [key: string]: unknown;
}

export default function OrderConfirmation() {
    const { orderNumber } = usePage<PageProps>().props;

    const [currentUser] = useState<StoreUser | null>(() => getUser());

    const queryParams = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
    const queryEmail = queryParams.get('email') ?? '';
    const queryPhone = queryParams.get('phone') ?? '';

    const [order, setOrder] = useState<Order | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [lookup, setLookup] = useState<LookupForm>({
        email: queryEmail,
        phone: queryPhone,
        order_number: orderNumber ?? '',
    });
    const [lookupBusy, setLookupBusy] = useState(false);
    const [lookupError, setLookupError] = useState<string | null>(null);
    const [showLookup, setShowLookup] = useState(true);
    const [retrying, setRetrying] = useState(false);
    const [retryError, setRetryError] = useState<string | null>(null);
    const retryKeyRef = useRef<string | null>(null);

    const fetchOrder = (identifier: { email?: string; phone?: string }, orderNum: string) => {
        setLoading(true);
        setError(null);

        void apiStore<Order>('/orders/lookup', {
            body: { ...identifier, order_number: orderNum },
        }).then((res) => {
            setLoading(false);

            if (res.ok && res.data) {
                setOrder(res.data as Order);
                setShowLookup(false);
            } else {
                setError(res.message ?? 'We couldn\'t find an order matching those details.');
            }
        });
    };

    useEffect(() => {
        if (!orderNumber) {
            return;
        }

        const identifier: { email?: string; phone?: string } = {};

        if (queryEmail.trim()) {
            identifier.email = queryEmail.trim();
        } else if (queryPhone.trim()) {
            identifier.phone = queryPhone.trim();
        } else if (currentUser?.phone) {
            identifier.phone = currentUser.phone;
        } else if (currentUser?.email) {
            identifier.email = currentUser.email;
        }

        if (identifier.email || identifier.phone) {
            fetchOrder(identifier, orderNumber);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [orderNumber]);

    const handleRetryPayment = () => {
        if (!order || retrying) {
            return;
        }

        const identifier: { email?: string; phone?: string } = {};

        if (lookup.email.trim()) {
            identifier.email = lookup.email.trim();
        } else if (lookup.phone.trim()) {
            identifier.phone = lookup.phone.trim();
        } else if (currentUser?.phone) {
            identifier.phone = currentUser.phone;
        } else if (currentUser?.email) {
            identifier.email = currentUser.email;
        } else {
            setRetryError('We need your email or phone number to retry payment.');

            return;
        }

        if (!retryKeyRef.current) {
            retryKeyRef.current =
                typeof crypto !== 'undefined' && crypto.randomUUID
                    ? crypto.randomUUID()
                    : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        }

        setRetrying(true);
        setRetryError(null);

        void apiStore<{
            order_number: string;
            status: string;
            payment: { id: number; redirect_url: string | null; replayed: boolean };
        }>(`/orders/${order.order_number}/retry-payment`, {
            body: identifier,
            headers: { 'Idempotency-Key': retryKeyRef.current },
        }).then((res) => {
            setRetrying(false);

            if (res.ok && res.data) {
                const redirectUrl = res.data.payment?.redirect_url;

                if (redirectUrl) {
                    window.location.href = redirectUrl;

                    return;
                }

                fetchOrder(identifier, order.order_number);
            } else {
                setRetryError(res.message ?? 'Payment retry failed. Please try again.');
            }
        });
    };

    const handleLookup = (e: React.FormEvent) => {
        e.preventDefault();
        setLookupBusy(true);
        setLookupError(null);

        const identifier: { email?: string; phone?: string } = {};
        if (lookup.email.trim()) {
            identifier.email = lookup.email.trim();
        }
        if (lookup.phone.trim()) {
            identifier.phone = lookup.phone.trim();
        }

        if (!identifier.email && !identifier.phone) {
            setLookupBusy(false);
            setLookupError('Please enter an email or phone number.');
            return;
        }

        void apiStore<Order>('/orders/lookup', {
            body: { ...identifier, order_number: lookup.order_number },
        }).then((res) => {
            setLookupBusy(false);

            if (res.ok && res.data) {
                setOrder(res.data as Order);
                setShowLookup(false);
            } else {
                setLookupError(res.message ?? 'We couldn\'t find an order matching those details.');
            }
        });
    };

    if (showLookup && !order) {
        return (
            <StoreLayout title="Find Your Order">
                <div className="mx-auto max-w-lg px-4 py-12">
                    <div className="rounded-lg border border-[var(--store-border)] p-6 text-center">
                        <Search className="mx-auto mb-4 h-10 w-10 text-[var(--store-muted)]" />
                        <h1 className="mb-2 text-xl font-bold">Find Your Order</h1>
                        <p className="mb-6 text-sm text-[var(--store-muted)]">
                            Enter your email or phone number and order number to view order details.
                        </p>

                        <form onSubmit={handleLookup} className="space-y-4 text-left">
                            <div>
                                <label className="mb-1 block text-sm font-medium">Order Number</label>
                                <input
                                    type="text"
                                    required
                                    value={lookup.order_number}
                                    onChange={(e) => setLookup({ ...lookup, order_number: e.target.value })}
                                    className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                                    placeholder="ORD-20260829-XXXXXX"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium">Email or Phone Number</label>
                                <input
                                    type="text"
                                    required
                                    value={lookup.email || lookup.phone}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        if (val.includes('@')) {
                                            setLookup({ ...lookup, email: val, phone: '' });
                                        } else {
                                            setLookup({ ...lookup, phone: val, email: '' });
                                        }
                                    }}
                                    className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                                    placeholder="you@example.com or 01XXXXXXXXX"
                                />
                            </div>

                            {lookupError && <p className="text-sm text-red-500">{lookupError}</p>}

                            <StoreButton type="submit" disabled={lookupBusy} className="w-full">
                                {lookupBusy ? 'Looking up...' : 'Find Order'}
                            </StoreButton>
                        </form>
                    </div>
                </div>
            </StoreLayout>
        );
    }

    if (loading) {
        return (
            <StoreLayout title="Order Confirmation">
                <div className="mx-auto max-w-2xl px-4 py-12 text-center text-[var(--store-muted)]">Loading order...</div>
            </StoreLayout>
        );
    }

    if (error || !order) {
        return (
            <StoreLayout title="Order Not Found">
                <div className="mx-auto max-w-2xl px-4 py-12 text-center">
                    <h1 className="mb-4 text-2xl font-bold">Order not found</h1>
                    <p className="mb-6 text-[var(--store-muted)]">{error ?? 'We couldn\'t find this order.'}</p>
                    <button
                        type="button"
                        onClick={() => {
                            setShowLookup(true);
                            setOrder(null);
                            setError(null);
                        }}
                        className="rounded-lg bg-[var(--store-accent)] px-6 py-2.5 text-sm font-semibold text-white hover:opacity-90"
                    >
                        Look up another order
                    </button>
                </div>
            </StoreLayout>
        );
    }

    const hasDiscounts = order.applied_discounts && order.applied_discounts.length > 0;
    const hasCoupon = !!order.coupon_code;

    return (
        <StoreLayout title="Order Confirmed">
            <div className="mx-auto max-w-2xl px-4 py-8">
                {/* Header */}
                <div className="mb-8 text-center">
                    <CheckCircle className="mx-auto mb-4 h-14 w-14 text-green-500" />
                    <h1 className="mb-2 text-2xl font-bold">Thank you for your order!</h1>
                    <p className="text-[var(--store-muted)]">
                        Order <span className="font-mono font-semibold">{order.order_number}</span> has been placed.
                    </p>
                    {order.guest_email && (
                        <p className="mt-1 text-sm text-[var(--store-muted)]">
                            Confirmation sent to <span className="font-medium">{order.guest_email}</span>
                        </p>
                    )}
                </div>

                {/* Order Items */}
                <div className="mb-4 rounded-lg border border-[var(--store-border)] p-6">
                    <div className="mb-4 flex items-center gap-2">
                        <Package className="h-5 w-5 text-[var(--store-muted)]" />
                        <h2 className="text-lg font-semibold">Order Items</h2>
                    </div>

                    <div className="divide-y divide-[var(--store-border)]">
                        {order.items.map((item, i) => (
                            <div key={i} className="py-3 first:pt-0 last:pb-0">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <p className="font-medium">{item.name}</p>
                                        <p className="text-sm text-[var(--store-muted)]">
                                            SKU: {item.sku}
                                        </p>
                                        <p className="text-sm text-[var(--store-muted)]">
                                            {formatPrice(item.unit_price)} × {item.quantity}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-medium">{formatPrice(item.total)}</p>
                                        {item.discount_amount > 0 && (
                                            <p className="text-sm text-green-600">
                                                -{formatPrice(item.discount_amount)} off
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Price Breakdown */}
                <div className="mb-4 rounded-lg border border-[var(--store-border)] p-6">
                    <div className="mb-3 flex items-center gap-2">
                        <Tag className="h-5 w-5 text-[var(--store-muted)]" />
                        <h2 className="text-lg font-semibold">Price Details</h2>
                    </div>

                    <div className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-[var(--store-muted)]">Subtotal ({order.items.length} item{order.items.length !== 1 ? 's' : ''})</span>
                            <span>{formatPrice(order.subtotal)}</span>
                        </div>

                        {order.discount_total > 0 && (
                            <div className="flex justify-between text-green-600">
                                <span>Discount</span>
                                <span>-{formatPrice(order.discount_total)}</span>
                            </div>
                        )}

                        {hasCoupon && (
                            <div className="text-green-600">
                                <span>Coupon applied: {order.coupon_code}</span>
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

                        <div className="border-t border-[var(--store-border)] pt-2">
                            <div className="flex justify-between text-base font-bold">
                                <span>Total</span>
                                <span>{formatPrice(order.total)}</span>
                            </div>
                        </div>
                    </div>

                    {/* Discount Summary */}
                    {order.discount_total > 0 && (
                        <div className="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
                            <p className="font-medium">You saved {formatPrice(order.discount_total)} on this order!</p>
                            {hasDiscounts && (
                                <ul className="mt-1 list-inside list-disc text-xs">
                                    {order.applied_discounts.map((d) => (
                                        <li key={d.id}>{d.name}</li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                </div>

                {/* Shipping Info */}
                <div className="mb-4 rounded-lg border border-[var(--store-border)] p-6">
                    <div className="mb-3 flex items-center gap-2">
                        <Truck className="h-5 w-5 text-[var(--store-muted)]" />
                        <h2 className="text-lg font-semibold">Shipping Details</h2>
                    </div>

                    <div className="space-y-1 text-sm">
                        <p><span className="text-[var(--store-muted)]">Name:</span> {order.shipping_name}</p>
                        <p><span className="text-[var(--store-muted)]">Phone:</span> {order.shipping_phone}</p>
                        <p><span className="text-[var(--store-muted)]">Address:</span> {order.shipping_address}</p>
                        <p><span className="text-[var(--store-muted)]">City:</span> {order.shipping_city}{order.shipping_state ? `, ${order.shipping_state}` : ''}</p>
                        <p><span className="text-[var(--store-muted)]">Country:</span> {order.shipping_country}</p>
                    </div>
                </div>

                {/* Status */}
                <div className="mb-6 rounded-lg border border-[var(--store-border)] p-6">
                    <h2 className="mb-2 text-lg font-semibold">Order Status</h2>
                    <div className="rounded-md bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
                        {order.status === 'confirmed'
                            ? 'Your order is confirmed. We\'ll start processing it shortly.'
                            : order.status === 'pending'
                                ? 'Your order is pending payment. Complete payment to confirm.'
                                : `Status: ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}`}
                    </div>
                    {order.status === 'pending' && (
                        <div className="mt-3">
                            <button
                                type="button"
                                onClick={handleRetryPayment}
                                disabled={retrying}
                                className="inline-flex items-center rounded-lg bg-[var(--store-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {retrying ? 'Starting secure payment...' : 'Pay now'}
                            </button>
                            {retryError && <p className="mt-2 text-xs text-red-500">{retryError}</p>}
                        </div>
                    )}
                </div>

                {/* Tax Invoice (ZATCA) */}
                {order.zatca && (
                    <div className="mb-6 rounded-lg border border-[var(--store-border)] p-6">
                        <h2 className="mb-2 text-lg font-semibold">Tax Invoice</h2>
                        <div className="flex flex-col items-center gap-3 sm:flex-row sm:gap-6">
                            <div
                                className="h-36 w-36 flex-shrink-0 [&_svg]:h-full [&_svg]:w-full"
                                dangerouslySetInnerHTML={{ __html: order.zatca.qr_svg }}
                            />
                            <div className="space-y-1 text-center text-sm sm:text-left">
                                <p className="text-lg font-semibold" dir="rtl" lang="ar">{order.zatca.seller_name_ar}</p>
                                <p><span className="text-[var(--store-muted)]">VAT №:</span> <span className="font-mono">{order.zatca.vat_number}</span></p>
                                <p><span className="text-[var(--store-muted)]">VAT included:</span> {formatPrice(order.tax_amount)}</p>
                                <p className="text-xs text-[var(--store-muted)]">Scan to verify this invoice with ZATCA.</p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tracking */}
                {order.shipments && order.shipments.length > 0 && (
                    <div className="mb-6 rounded-lg border border-[var(--store-border)] p-6">
                        <div className="mb-3 flex items-center gap-2">
                            <Truck className="h-5 w-5 text-[var(--store-muted)]" />
                            <h2 className="text-lg font-semibold">Tracking</h2>
                        </div>
                        <div className="space-y-3">
                            {order.shipments.map((shipment) => (
                                <div key={shipment.id} className="rounded-md bg-blue-50 p-4 text-sm dark:bg-blue-900/20">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="font-medium text-blue-800 dark:text-blue-300">{shipment.courier}</p>
                                            <p className="text-blue-700 dark:text-blue-400">
                                                Tracking: <span className="font-mono">{shipment.tracking_number}</span>
                                            </p>
                                        </div>
                                        <span className="rounded-lg bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800 dark:bg-blue-800 dark:text-blue-200">
                                            {shipment.status.replace('_', ' ')}
                                        </span>
                                    </div>
                                    {shipment.note && (
                                        <p className="mt-2 text-xs text-blue-600 dark:text-blue-300 italic">{shipment.note}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Notes */}
                {order.notes && (
                    <div className="mb-6 rounded-lg border border-[var(--store-border)] p-6">
                        <h2 className="mb-2 text-lg font-semibold">Order Notes</h2>
                        <p className="text-sm text-[var(--store-muted)]">{order.notes}</p>
                    </div>
                )}

                {/* Actions */}
                <div className="flex justify-center gap-4">
                    <StoreButton href="/products">
                        Continue shopping
                    </StoreButton>
                    <button
                        type="button"
                        onClick={() => {
                            setShowLookup(true);
                            setOrder(null);
                            setLookup({ email: '', phone: '', order_number: '' });
                        }}
                        className="rounded-lg border border-[var(--store-border)] px-6 py-2.5 text-sm font-medium hover:bg-[var(--store-card-hover)]"
                    >
                        Look up another order
                    </button>
                </div>
            </div>
        </StoreLayout>
    );
}
