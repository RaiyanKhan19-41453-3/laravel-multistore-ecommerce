import StoreLayout from '@/layouts/store-layout';
import StoreButton from '@/components/store/store-button';
import { apiStore, getUser } from '@/lib/auth';
import { formatPrice } from '@/lib/format';
import type { CartSummary } from '@/types';
import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { CreditCard, Info, Truck } from 'lucide-react';

const BD_DIVISIONS = ['Dhaka', 'Chattogram', 'Rajshahi', 'Khulna', 'Barishal', 'Sylhet', 'Rangpur', 'Mymensingh'];

interface PaymentMethodOption {
    value: string;
    label: string;
}

interface ShippingForm {
    guest_email: string;
    phone: string;
    delivery_phone: string;
    use_same_phone: boolean;
    shipping_name: string;
    shipping_address: string;
    shipping_city: string;
    shipping_state: string;
    shipping_postal_code: string;
    shipping_country: string;
    payment_method: string;
    notes: string;
}

interface ShippingRateOption {
    id: number;
    method_id: number;
    method_name: string;
    method_description: string | null;
    estimated_days: number | null;
    price: number;
    free_shipping_min: number | null;
    shipping_cost: number;
    is_free: boolean;
}

export default function Checkout() {
    const [cart, setCart] = useState<CartSummary | null>(null);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    const user = getUser();

    const [form, setForm] = useState<ShippingForm>({
        guest_email: user?.email ?? '',
        phone: user?.phone ?? '',
        delivery_phone: '',
        use_same_phone: true,
        shipping_name: user?.name ?? '',
        shipping_address: '',
        shipping_city: '',
        shipping_state: '',
        shipping_postal_code: '',
        shipping_country: 'Bangladesh',
        payment_method: 'cod',
        notes: '',
    });

    const [accountNudge, setAccountNudge] = useState(false);
    const [emailChecked, setEmailChecked] = useState(false);
    const [availableCities, setAvailableCities] = useState<string[]>([]);
    const [shippingRates, setShippingRates] = useState<ShippingRateOption[]>([]);
    const [selectedRateId, setSelectedRateId] = useState<number | null>(null);
    const [shippingLoading, setShippingLoading] = useState(false);
    const [paymentMethods, setPaymentMethods] = useState<PaymentMethodOption[]>([]);
    const emailCheckTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const ratesRequestId = useRef(0);

    const fetchCart = () => {
        void apiStore<CartSummary>('/cart').then((res) => {
            if (res.ok && res.data) {
                setCart(res.data);
            }
            setLoading(false);
        });
    };

    const fetchCities = () => {
        void apiStore<string[]>('/shipping/cities').then((res) => {
            if (res.ok && res.data) {
                setAvailableCities(res.data);
            }
        });
    };

    useEffect(() => {
        fetchCart();
        fetchCities();

        void apiStore<{ methods: PaymentMethodOption[] }>('/payment-methods').then((res) => {
            const methods = res.ok ? (res.data?.methods ?? []) : [];

            if (methods.length > 0) {
                setPaymentMethods(methods);

                setForm((prev) => {
                    const stillEnabled = methods.some((m) => m.value === prev.payment_method);

                    return {
                        ...prev,
                        payment_method: stillEnabled ? prev.payment_method : (methods[0]?.value ?? 'cod'),
                    };
                });
            }
        });
    }, []);

    useEffect(() => {
        return () => {
            if (emailCheckTimer.current) {
                clearTimeout(emailCheckTimer.current);
            }
        };
    }, []);

    const setField = (field: keyof ShippingForm, value: string | boolean) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        setFieldErrors((prev) => {
            const next = { ...prev };
            delete next[field];
            return next;
        });
    };

    const checkEmail = useCallback((email: string) => {
        if (!email || user) {
            return;
        }

        void apiStore<{ exists: boolean }>('/auth/check-email', {
            body: { email },
        }).then((res) => {
            if (res.ok && res.data?.exists) {
                setAccountNudge(true);
            }
            setEmailChecked(true);
        });
    }, [user]);

    const handleEmailChange = (value: string) => {
        setField('guest_email', value);
        setEmailChecked(false);
        setAccountNudge(false);
    };

    const handleEmailBlur = (e: React.FocusEvent<HTMLInputElement>) => {
        const email = e.target.value.trim();
        if (email && !emailChecked) {
            if (emailCheckTimer.current) {
                clearTimeout(emailCheckTimer.current);
            }
            emailCheckTimer.current = setTimeout(() => checkEmail(email), 300);
        }
    };

    const handleCityChange = (value: string) => {
        setField('shipping_city', value);
        setSelectedRateId(null);
        setShippingRates([]);
        if (value) {
            fetchRates(value, cart?.subtotal ?? 0);
        }
    };

    const fetchRates = (city: string, subtotal: number) => {
        const requestId = ++ratesRequestId.current;
        setShippingLoading(true);
        void apiStore<ShippingRateOption[]>(`/shipping/rates?city=${encodeURIComponent(city)}&subtotal=${subtotal}`).then((res) => {
            if (requestId !== ratesRequestId.current) {
                return;
            }

            if (!res.ok || !res.data) {
                setShippingRates([]);
                setSelectedRateId(null);
                setShippingLoading(false);

                return;
            }

            const rates = res.data;
            setShippingRates(rates);

            setSelectedRateId((prev) => {
                if (prev !== null && rates.some((rate) => rate.id === prev)) {
                    return prev;
                }

                return rates.length === 1 ? rates[0].id : null;
            });
            setShippingLoading(false);
        });
    };

    const cartSubtotal = cart?.subtotal ?? 0;

    useEffect(() => {
        if (form.shipping_city) {
            fetchRates(form.shipping_city, cartSubtotal);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cartSubtotal]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        setFieldErrors({});

        if (!form.shipping_city) {
            setError('Please enter your city.');
            setSubmitting(false);
            return;
        }

        if (shippingRates.length === 0 && !shippingLoading) {
            setError('No shipping options available for your city. Please try a different city or contact support.');
            setSubmitting(false);
            return;
        }

        if (!selectedRateId) {
            setError('Please select a shipping method.');
            setSubmitting(false);
            return;
        }

        const body: Record<string, string | boolean | number> = {
            shipping_name: form.shipping_name,
            phone: form.phone,
            delivery_phone: form.use_same_phone ? '' : form.delivery_phone,
            shipping_address: form.shipping_address,
            shipping_city: form.shipping_city,
            shipping_state: form.shipping_state,
            shipping_postal_code: form.shipping_postal_code,
            shipping_country: form.shipping_country,
            payment_method: form.payment_method,
            notes: form.notes,
        };

        if (selectedRateId) {
            body.shipping_rate_id = selectedRateId;
        }

        if (!user) {
            body.guest_email = form.guest_email;
        }

        void apiStore<{
            order: { id: number; order_number: string; status: string; total: number };
            payment?: { id: number; redirect_url: string | null };
        }>('/checkout', {
            body,
        }).then((res) => {
            setSubmitting(false);

            if (res.ok && res.data) {
                const redirectUrl = res.data.payment?.redirect_url;

                if (redirectUrl) {
                    window.location.href = redirectUrl;

                    return;
                }

                const params = new URLSearchParams();

                if (form.guest_email) {
                    params.set('email', form.guest_email);
                }

                if (form.phone) {
                    params.set('phone', form.phone);
                }

                const query = params.toString();

                router.visit(`/order-confirmation/${res.data.order.order_number}${query ? `?${query}` : ''}`);
            } else {
                setError(res.message ?? 'Checkout failed. Please try again.');

                if (res.errors) {
                    const mapped: Record<string, string> = {};

                    for (const [field, messages] of Object.entries(res.errors)) {
                        mapped[field] = messages[0] ?? '';
                    }

                    setFieldErrors(mapped);
                }
            }
        });
    };

    if (loading) {
        return (
            <StoreLayout title="Checkout">
                <div className="mx-auto max-w-4xl px-4 py-12 text-[var(--store-muted)]">Loading checkout...</div>
            </StoreLayout>
        );
    }

    if (!cart || cart.items.length === 0) {
        return (
            <StoreLayout title="Checkout">
                <div className="mx-auto max-w-4xl px-4 py-12 text-center">
                    <h1 className="mb-4 text-2xl font-bold">Your cart is empty</h1>
                    <p className="mb-6 text-[var(--store-muted)]">Add some items before checking out.</p>
                    <StoreButton href="/products">
                        Browse products
                    </StoreButton>
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
        <StoreLayout title="Checkout">
            <div className="store-container py-8">
                <h1 className="text-2xl font-bold tracking-tight md:text-3xl">Checkout</h1>

                {/* Steps */}
                <ol className="mt-5 flex items-center gap-2 text-xs font-semibold">
                    <li className="flex items-center gap-1.5 rounded-lg border border-[var(--store-border)] px-3.5 py-1.5 text-[var(--store-muted)]">
                        <span className="flex h-4 w-4 items-center justify-center rounded-lg bg-[var(--store-success-soft)] text-[10px] text-[var(--store-success)]">✓</span>
                        Cart
                    </li>
                    <li className="h-px w-8 bg-[var(--store-border)]" />
                    <li className="flex items-center gap-1.5 rounded-lg bg-[var(--store-text)] px-3.5 py-1.5 text-[var(--store-bg)]">
                        <span className="flex h-4 w-4 items-center justify-center rounded-lg bg-[var(--store-bg)] text-[10px] text-[var(--store-text)]">2</span>
                        Details
                    </li>
                    <li className="h-px w-8 bg-[var(--store-border)]" />
                    <li className="flex items-center gap-1.5 rounded-lg border border-[var(--store-border)] px-3.5 py-1.5 text-[var(--store-muted)]">
                        <span className="flex h-4 w-4 items-center justify-center rounded-lg bg-[var(--store-card-hover)] text-[10px]">3</span>
                        Done
                    </li>
                </ol>

                {error && (
                    <div className="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                        {error}
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <div className="grid gap-8 lg:grid-cols-3">
                        {/* Shipping & Payment */}
                        <div className="space-y-6 lg:col-span-2">
                            {/* Contact Information: guests only */}
                            {!user && (
                                <section className="rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5 md:p-6">
                                    <h2 className="mb-4 text-lg font-semibold">Contact Information</h2>
                                    <div className="mb-4">
                                        <label className="mb-1 block text-sm font-medium">Email</label>
                                        <input
                                            type="email"
                                            required
                                            value={form.guest_email}
                                            onChange={(e) => handleEmailChange(e.target.value)}
                                            onBlur={handleEmailBlur}
                                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                            placeholder="you@example.com"
                                        />
                                        {fieldErrors.guest_email && (
                                            <p className="mt-1 text-xs text-red-500">{fieldErrors.guest_email}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">Phone</label>
                                        <input
                                            type="text"
                                            required
                                            value={form.phone}
                                            onChange={(e) => setField('phone', e.target.value)}
                                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                            placeholder="01XXXXXXXXX"
                                        />
                                        {fieldErrors.phone && (
                                            <p className="mt-1 text-xs text-red-500">{fieldErrors.phone}</p>
                                        )}
                                    </div>
                                    <p className="mt-2 text-xs text-[var(--store-muted)]">
                                        We&apos;ll send order updates to this email.
                                    </p>

                                    {accountNudge && (
                                        <div className="mt-3 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-800 dark:bg-blue-900/20">
                                            <div className="flex items-start gap-2">
                                                <Info className="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-600 dark:text-blue-400" />
                                                <div className="text-sm">
                                                    <p className="text-blue-700 dark:text-blue-300">
                                                        You may already have an account. Sign in to track your order and manage your purchases.
                                                    </p>
                                                    <div className="mt-2 flex gap-3">
                                                        <Link
                                                            href={`/account/login?redirect=/checkout`}
                                                            className="text-sm font-semibold text-blue-700 hover:underline dark:text-blue-300"
                                                        >
                                                            Sign in
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            onClick={() => setAccountNudge(false)}
                                                            className="text-sm text-blue-600 hover:underline dark:text-blue-400"
                                                        >
                                                            Continue as guest
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </section>
                            )}

                            {/* Shipping Address */}
                            <section className="rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5 md:p-6">
                                <h2 className="mb-4 text-lg font-semibold">Shipping Address</h2>
                                <div className="space-y-4">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">Full Name</label>
                                        <input
                                            type="text"
                                            required
                                            value={form.shipping_name}
                                            onChange={(e) => setField('shipping_name', e.target.value)}
                                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                        />
                                        {fieldErrors.shipping_name && (
                                            <p className="mt-1 text-xs text-red-500">{fieldErrors.shipping_name}</p>
                                        )}
                                    </div>

                                    {/* Delivery Phone */}
                                    <div>
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={form.use_same_phone}
                                                onChange={(e) => {
                                                    setField('use_same_phone', e.target.checked);
                                                    if (e.target.checked) {
                                                        setField('delivery_phone', '');
                                                    }
                                                }}
                                                className="accent-[var(--store-accent)]"
                                            />
                                            <span className="text-sm font-medium">Use contact phone for delivery</span>
                                        </label>
                                        {!form.use_same_phone && (
                                            <div className="mt-2">
                                                <label className="mb-1 block text-sm font-medium">Delivery Phone</label>
                                                <input
                                                    type="text"
                                                    required
                                                    value={form.delivery_phone}
                                                    onChange={(e) => setField('delivery_phone', e.target.value)}
                                                    className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                                    placeholder="01XXXXXXXXX"
                                                />
                                                {fieldErrors.delivery_phone && (
                                                    <p className="mt-1 text-xs text-red-500">{fieldErrors.delivery_phone}</p>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-sm font-medium">Address</label>
                                        <textarea
                                            required
                                            rows={2}
                                            value={form.shipping_address}
                                            onChange={(e) => setField('shipping_address', e.target.value)}
                                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                            placeholder="Street address, house number, apartment..."
                                        />
                                        {fieldErrors.shipping_address && (
                                            <p className="mt-1 text-xs text-red-500">{fieldErrors.shipping_address}</p>
                                        )}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">City</label>
                                        <select
                                            required
                                            value={form.shipping_city}
                                            onChange={(e) => handleCityChange(e.target.value)}
                                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition focus:border-[var(--store-accent)]"
                                        >
                                            <option value="">Select a city</option>
                                            {availableCities.map((city) => (
                                                <option key={city} value={city}>{city}</option>
                                            ))}
                                        </select>
                                        {fieldErrors.shipping_city && (
                                            <p className="mt-1 text-xs text-red-500">{fieldErrors.shipping_city}</p>
                                        )}
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium">Division</label>
                                            <select
                                                value={form.shipping_state}
                                                onChange={(e) => setField('shipping_state', e.target.value)}
                                                className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition focus:border-[var(--store-accent)]"
                                            >
                                                <option value="">Select a division (optional)</option>
                                                {BD_DIVISIONS.map((division) => (
                                                    <option key={division} value={division}>{division}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium">Postal Code</label>
                                            <input
                                                type="text"
                                                value={form.shipping_postal_code}
                                                onChange={(e) => setField('shipping_postal_code', e.target.value)}
                                                className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {/* Shipping Method */}
                            {form.shipping_city && (
                                <section className="rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5 md:p-6">
                                    <h2 className="mb-1 text-lg font-semibold">Shipping Method</h2>
                                    <p className="mb-4 text-xs text-[var(--store-muted)]">Costs shown are estimates. Final amount is calculated server-side when you place your order.</p>
                                    {shippingLoading ? (
                                        <p className="text-sm text-[var(--store-muted)]">Loading shipping options...</p>
                                    ) : shippingRates.length === 0 ? (
                                        <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                            No shipping options found for &ldquo;{form.shipping_city}&rdquo;. Try a different city spelling or contact support.
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            {shippingRates.map((rate) => (
                                                <label
                                                    key={rate.id}
                                                    className={`flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 transition ${
                                                        selectedRateId === rate.id
                                                            ? 'border-[var(--store-accent)] bg-[var(--store-accent)]/5'
                                                            : 'border-[var(--store-border)] hover:bg-[var(--store-card-hover)]'
                                                    }`}
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <input
                                                            type="radio"
                                                            name="shipping_rate"
                                                            value={rate.id}
                                                            checked={selectedRateId === rate.id}
                                                            onChange={() => setSelectedRateId(rate.id)}
                                                            className="accent-[var(--store-accent)]"
                                                        />
                                                        <div>
                                                            <span className="text-sm font-medium">{rate.method_name}</span>
                                                            {rate.method_description && (
                                                                <p className="text-xs text-[var(--store-muted)]">{rate.method_description}</p>
                                                            )}
                                                            {rate.estimated_days && (
                                                                <p className="text-xs text-[var(--store-muted)]">{rate.estimated_days} business days</p>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <span className="text-sm font-semibold">
                                                        {rate.is_free ? 'Free' : formatPrice(rate.shipping_cost)}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </section>
                            )}

                            {/* Payment Method */}
                            <section className="rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5 md:p-6">
                                <h2 className="mb-4 text-lg font-semibold">Payment Method</h2>
                                <div className="space-y-2">
                                    {paymentMethods.map((method) => (
                                        <label
                                            key={method.value}
                                            className={`flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 transition ${
                                                form.payment_method === method.value
                                                    ? 'border-[var(--store-accent)] bg-[var(--store-accent)]/5'
                                                    : 'border-[var(--store-border)] hover:bg-[var(--store-card-hover)]'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="payment_method"
                                                value={method.value}
                                                checked={form.payment_method === method.value}
                                                onChange={(e) => setField('payment_method', e.target.value)}
                                                className="accent-[var(--store-accent)]"
                                            />
                                            {method.value === 'cod' ? (
                                                <Truck className="h-4 w-4 text-[var(--store-muted)]" />
                                            ) : (
                                                <CreditCard className="h-4 w-4 text-[var(--store-muted)]" />
                                            )}
                                            <span className="text-sm font-medium">{method.label}</span>
                                        </label>
                                    ))}
                                </div>
                                {fieldErrors.payment_method && (
                                    <p className="mt-2 text-xs text-red-500">{fieldErrors.payment_method}</p>
                                )}
                            </section>

                            {/* Notes */}
                            <section className="rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5 md:p-6">
                                <h2 className="mb-4 text-lg font-semibold">Order Notes (optional)</h2>
                                <textarea
                                    rows={2}
                                    value={form.notes}
                                    onChange={(e) => setField('notes', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                    placeholder="Any special instructions for delivery..."
                                />
                            </section>
                        </div>

                        {/* Order Summary */}
                        <div className="lg:col-span-1">
                            <div className="rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5 lg:sticky lg:top-24 md:p-6">
                                <h2 className="mb-4 text-lg font-semibold">Order Summary</h2>

                                <div className="space-y-3 text-sm">
                                    <div className="space-y-1.5">
                                        {cart.items.map((item) => (
                                            <div key={item.id} className="flex justify-between">
                                                <span className="truncate text-[var(--store-muted)]">
                                                    {item.product.name}
                                                    {item.quantity > 1 ? ` x${item.quantity}` : ''}
                                                </span>
                                                <span>{formatPrice(item.line_total)}</span>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="border-t border-[var(--store-border)] pt-2">
                                        <div className="flex justify-between">
                                            <span className="text-[var(--store-muted)]">Subtotal</span>
                                            <span>{formatPrice(cart.subtotal)}</span>
                                        </div>
                                    </div>

                                    {discounts.length > 0 && (
                                        <div className="space-y-1 border-t border-[var(--store-border)] pt-2">
                                            {discounts.map((d, i) => (
                                                <div key={i} className="flex justify-between text-green-600">
                                                    <span className="truncate">{d.name}</span>
                                                    <span className="flex-shrink-0">-{formatPrice(d.amount)}</span>
                                                </div>
                                            ))}
                                            <div className="flex justify-between border-t border-[var(--store-border)] pt-1 font-medium text-green-600">
                                                <span>You save</span>
                                                <span>-{formatPrice(cart.discount_total)}</span>
                                            </div>
                                        </div>
                                    )}

                                    {selectedRateId && (() => {
                                        const selectedRate = shippingRates.find((r) => r.id === selectedRateId);
                                        if (!selectedRate) return null;
                                        return (
                                            <div className="flex justify-between border-t border-[var(--store-border)] pt-2">
                                                <span className="text-[var(--store-muted)]">Shipping{selectedRate.method_name ? ` (${selectedRate.method_name})` : ''}</span>
                                                <span>{selectedRate.is_free ? 'Free' : formatPrice(selectedRate.shipping_cost)}</span>
                                            </div>
                                        );
                                    })()}

                                    <div className="border-t border-[var(--store-border)] pt-2">
                                        <div className="flex justify-between text-base font-bold">
                                            <span>Total</span>
                                            <span>{formatPrice(cart.total + (selectedRateId ? (shippingRates.find((r) => r.id === selectedRateId)?.shipping_cost ?? 0) : 0))}</span>
                                        </div>
                                    </div>
                                </div>

                                <StoreButton type="submit" disabled={submitting} className="mt-5 w-full">
                                    {submitting ? 'Placing order...' : 'Place Order'}
                                </StoreButton>

                                <Link
                                    href="/cart"
                                    className="mt-3 block text-center text-sm text-[var(--store-muted)] hover:underline"
                                >
                                    Back to cart
                                </Link>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </StoreLayout>
    );
}
