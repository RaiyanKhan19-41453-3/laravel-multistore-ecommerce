import StoreLayout from '@/layouts/store-layout';
import StoreButton from '@/components/store/store-button';
import ProductImage from '@/components/store/product-image';
import { apiStore, getUser } from '@/lib/auth';
import { formatPrice } from '@/lib/format';
import type { CartSummary } from '@/types';
import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    Banknote,
    Check,
    CreditCard,
    Info,
    LoaderCircle,
    Lock,
    MapPin,
    NotebookPen,
    Package,
    RotateCcw,
    ShieldCheck,
    ShoppingBag,
    TriangleAlert,
    Truck,
    User,
} from 'lucide-react';

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

const inputClass =
    'w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)] focus:ring-2 focus:ring-[var(--store-accent)]/20';

function SectionHeading({
    icon: Icon,
    title,
    subtitle,
}: {
    icon: typeof Truck;
    title: string;
    subtitle?: string;
}) {
    return (
        <div className="mb-5 flex items-center gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--store-accent)]/10 text-[var(--store-accent)]">
                <Icon className="h-5 w-5" />
            </span>
            <div>
                <h2 className="text-base font-semibold">{title}</h2>
                {subtitle && <p className="text-xs text-[var(--store-muted)]">{subtitle}</p>}
            </div>
        </div>
    );
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
    const [failedOrderNumber, setFailedOrderNumber] = useState<string | null>(null);
    const [retrying, setRetrying] = useState(false);
    const retryKeyRef = useRef<string | null>(null);
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
        setFailedOrderNumber(null);
        retryKeyRef.current = null;

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

                // The order exists but payment never started: keep its number
                // so the customer can retry on the same order. One key per
                // failure, so double-clicks replay instead of duplicating.
                const failedNumber = (res.data as { order?: { order_number?: string } } | null)?.order
                    ?.order_number;

                if (failedNumber) {
                    setFailedOrderNumber(failedNumber);
                    retryKeyRef.current =
                        typeof crypto !== 'undefined' && crypto.randomUUID
                            ? crypto.randomUUID()
                            : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
                }

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

    const handleRetryPayment = () => {
        if (!failedOrderNumber || retrying) {
            return;
        }

        if (!retryKeyRef.current) {
            retryKeyRef.current =
                typeof crypto !== 'undefined' && crypto.randomUUID
                    ? crypto.randomUUID()
                    : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        }

        setRetrying(true);

        const retryBody: Record<string, string> = {};

        if (!user) {
            if (form.guest_email) {
                retryBody.email = form.guest_email;
            }

            if (form.phone) {
                retryBody.phone = form.phone;
            }
        }

        void apiStore<{
            order_number: string;
            status: string;
            payment: { id: number; redirect_url: string | null; replayed: boolean };
        }>(`/orders/${failedOrderNumber}/retry-payment`, {
            body: retryBody,
            headers: { 'Idempotency-Key': retryKeyRef.current },
        }).then((res) => {
            setRetrying(false);

            if (res.ok && res.data) {
                const redirectUrl = res.data.payment?.redirect_url;

                setError(null);

                if (redirectUrl) {
                    window.location.href = redirectUrl;

                    return;
                }

                router.visit(`/order-confirmation/${res.data.order_number}`);
            } else {
                setError(res.message ?? 'Payment retry failed. Please try again.');
            }
        });
    };

    if (loading) {
        return (
            <StoreLayout title="Checkout">
                <div className="store-container py-8 md:py-10">
                    <div className="h-8 w-48 animate-pulse rounded-lg bg-[var(--store-card-hover)]" />
                    <div className="mt-6 grid gap-8 lg:grid-cols-3">
                        <div className="space-y-6 lg:col-span-2">
                            {[0, 1, 2].map((i) => (
                                <div key={i} className="h-44 animate-pulse rounded-2xl bg-[var(--store-card-hover)]" />
                            ))}
                        </div>
                        <div className="h-96 animate-pulse rounded-2xl bg-[var(--store-card-hover)]" />
                    </div>
                </div>
            </StoreLayout>
        );
    }

    if (!cart || cart.items.length === 0) {
        return (
            <StoreLayout title="Checkout">
                <div className="mx-auto max-w-4xl px-4 py-12 text-center md:py-20">
                    <span className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-[var(--store-card-hover)] text-[var(--store-muted)]">
                        <ShoppingBag className="h-8 w-8" />
                    </span>
                    <h1 className="mb-2 text-2xl font-bold">Your cart is empty</h1>
                    <p className="mb-6 text-sm text-[var(--store-muted)]">Add some items before checking out.</p>
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
            <div className="store-container py-8 md:py-10">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">Checkout</h1>
                        <p className="mt-1 text-sm text-[var(--store-muted)]">Almost there — review your details and place your order.</p>
                    </div>
                    <p className="flex items-center gap-1.5 text-xs font-medium text-[var(--store-muted)]">
                        <Lock className="h-3.5 w-3.5" />
                        Secure checkout
                    </p>
                </div>

                {/* Steps */}
                <ol className="mt-6 flex items-center gap-2 text-xs font-semibold">
                    <li className="flex items-center gap-2 rounded-full border border-[var(--store-border)] bg-[var(--store-card)] py-1.5 pr-4 pl-1.5 text-[var(--store-muted)]">
                        <span className="flex h-6 w-6 items-center justify-center rounded-full bg-[var(--store-success)] text-white">
                            <Check className="h-3.5 w-3.5" />
                        </span>
                        Cart
                    </li>
                    <li className="h-px w-6 bg-[var(--store-border)] sm:w-10" />
                    <li className="flex items-center gap-2 rounded-full bg-[var(--store-text)] py-1.5 pr-4 pl-1.5 text-[var(--store-bg)]">
                        <span className="flex h-6 w-6 items-center justify-center rounded-full bg-[var(--store-bg)]/20 text-xs font-bold">2</span>
                        Details
                    </li>
                    <li className="h-px w-6 bg-[var(--store-border)] sm:w-10" />
                    <li className="flex items-center gap-2 rounded-full border border-[var(--store-border)] bg-[var(--store-card)] py-1.5 pr-4 pl-1.5 text-[var(--store-muted)]">
                        <span className="flex h-6 w-6 items-center justify-center rounded-full bg-[var(--store-card-hover)] text-xs font-bold">3</span>
                        Done
                    </li>
                </ol>

                {error && (
                    <div className="mt-5 flex flex-col gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm sm:flex-row sm:items-center dark:border-red-800 dark:bg-red-900/20">
                        <div className="flex items-start gap-2.5 text-red-700 dark:text-red-300">
                            <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                            <p className="font-medium">{error}</p>
                        </div>
                        {failedOrderNumber && (
                            <button
                                type="button"
                                onClick={handleRetryPayment}
                                disabled={retrying}
                                className="inline-flex shrink-0 items-center gap-2 rounded-xl bg-[var(--store-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60 sm:ml-auto"
                            >
                                <RotateCcw className="h-4 w-4" />
                                {retrying ? 'Retrying payment...' : 'Try payment again'}
                            </button>
                        )}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="mt-6">
                    <div className="grid gap-8 lg:grid-cols-3">
                        {/* Shipping & Payment */}
                        <div className="space-y-6 lg:col-span-2">
                            {/* Contact Information: guests only */}
                            {!user && (
                                <section className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-card)] p-5 shadow-sm md:p-6">
                                    <SectionHeading icon={User} title="Contact Information" subtitle="We'll send order updates here" />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium">Email</label>
                                            <input
                                                type="email"
                                                required
                                                value={form.guest_email}
                                                onChange={(e) => handleEmailChange(e.target.value)}
                                                onBlur={handleEmailBlur}
                                                className={inputClass}
                                                placeholder="you@example.com"
                                            />
                                            {fieldErrors.guest_email && (
                                                <p className="mt-1 text-xs text-red-500">{fieldErrors.guest_email}</p>
                                            )}
                                        </div>
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium">Phone</label>
                                            <input
                                                type="text"
                                                required
                                                value={form.phone}
                                                onChange={(e) => setField('phone', e.target.value)}
                                                className={inputClass}
                                                placeholder="01XXXXXXXXX"
                                            />
                                            {fieldErrors.phone && (
                                                <p className="mt-1 text-xs text-red-500">{fieldErrors.phone}</p>
                                            )}
                                        </div>
                                    </div>

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
                            <section className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-card)] p-5 shadow-sm md:p-6">
                                <SectionHeading icon={MapPin} title="Shipping Address" subtitle="Where should we deliver your order?" />
                                <div className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium">Full Name</label>
                                            <input
                                                type="text"
                                                required
                                                value={form.shipping_name}
                                                onChange={(e) => setField('shipping_name', e.target.value)}
                                                className={inputClass}
                                                placeholder="Recipient's full name"
                                            />
                                            {fieldErrors.shipping_name && (
                                                <p className="mt-1 text-xs text-red-500">{fieldErrors.shipping_name}</p>
                                            )}
                                        </div>

                                        {/* Delivery Phone */}
                                        <div>
                                            <label className="mb-1.5 flex items-center gap-2 text-sm font-medium">
                                                <input
                                                    type="checkbox"
                                                    checked={form.use_same_phone}
                                                    onChange={(e) => {
                                                        setField('use_same_phone', e.target.checked);
                                                        if (e.target.checked) {
                                                            setField('delivery_phone', '');
                                                        }
                                                    }}
                                                    className="h-4 w-4 accent-[var(--store-accent)]"
                                                />
                                                Same as contact phone
                                            </label>
                                            {!form.use_same_phone ? (
                                                <input
                                                    type="text"
                                                    required
                                                    value={form.delivery_phone}
                                                    onChange={(e) => setField('delivery_phone', e.target.value)}
                                                    className={inputClass}
                                                    placeholder="01XXXXXXXXX"
                                                />
                                            ) : (
                                                <p className="rounded-xl bg-[var(--store-card-hover)] px-3.5 py-2.5 text-sm text-[var(--store-muted)]">
                                                    {form.phone || 'Uses the contact phone above'}
                                                </p>
                                            )}
                                            {fieldErrors.delivery_phone && (
                                                <p className="mt-1 text-xs text-red-500">{fieldErrors.delivery_phone}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">Address</label>
                                        <textarea
                                            required
                                            rows={2}
                                            value={form.shipping_address}
                                            onChange={(e) => setField('shipping_address', e.target.value)}
                                            className={inputClass}
                                            placeholder="Street address, house number, apartment..."
                                        />
                                        {fieldErrors.shipping_address && (
                                            <p className="mt-1 text-xs text-red-500">{fieldErrors.shipping_address}</p>
                                        )}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">City</label>
                                        <select
                                            required
                                            value={form.shipping_city}
                                            onChange={(e) => handleCityChange(e.target.value)}
                                            className={inputClass}
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
                                            <label className="mb-1.5 block text-sm font-medium">Division</label>
                                            <select
                                                value={form.shipping_state}
                                                onChange={(e) => setField('shipping_state', e.target.value)}
                                                className={inputClass}
                                            >
                                                <option value="">Select a division (optional)</option>
                                                {BD_DIVISIONS.map((division) => (
                                                    <option key={division} value={division}>{division}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium">Postal Code</label>
                                            <input
                                                type="text"
                                                value={form.shipping_postal_code}
                                                onChange={(e) => setField('shipping_postal_code', e.target.value)}
                                                className={inputClass}
                                                placeholder="1200"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {/* Shipping Method */}
                            {form.shipping_city && (
                                <section className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-card)] p-5 shadow-sm md:p-6">
                                    <SectionHeading icon={Truck} title="Shipping Method" subtitle="Costs are estimates — the final amount is calculated when you place your order." />
                                    {shippingLoading ? (
                                        <div className="space-y-2">
                                            {[0, 1].map((i) => (
                                                <div key={i} className="h-16 animate-pulse rounded-xl bg-[var(--store-card-hover)]" />
                                            ))}
                                        </div>
                                    ) : shippingRates.length === 0 ? (
                                        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                            No shipping options found for &ldquo;{form.shipping_city}&rdquo;. Try a different city spelling or contact support.
                                        </div>
                                    ) : (
                                        <div className="space-y-2.5">
                                            {shippingRates.map((rate) => {
                                                const active = selectedRateId === rate.id;

                                                return (
                                                    <label
                                                        key={rate.id}
                                                        className={`flex cursor-pointer items-center gap-3 rounded-xl border-2 px-4 py-3.5 transition ${
                                                            active
                                                                ? 'border-[var(--store-accent)] bg-[var(--store-accent)]/[0.04] shadow-sm'
                                                                : 'border-[var(--store-border)] hover:border-[var(--store-muted)]/40'
                                                        }`}
                                                    >
                                                        <span
                                                            className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition ${
                                                                active ? 'border-[var(--store-accent)]' : 'border-[var(--store-border)]'
                                                            }`}
                                                        >
                                                            {active && <span className="h-2.5 w-2.5 rounded-full bg-[var(--store-accent)]" />}
                                                        </span>
                                                        <input
                                                            type="radio"
                                                            name="shipping_rate"
                                                            value={rate.id}
                                                            checked={active}
                                                            onChange={() => setSelectedRateId(rate.id)}
                                                            className="sr-only"
                                                        />
                                                        <span className={`hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl sm:flex ${active ? 'bg-[var(--store-accent)]/10 text-[var(--store-accent)]' : 'bg-[var(--store-card-hover)] text-[var(--store-muted)]'}`}>
                                                            <Truck className="h-5 w-5" />
                                                        </span>
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block truncate text-sm font-semibold">{rate.method_name}</span>
                                                            {rate.method_description && (
                                                                <span className="block truncate text-xs text-[var(--store-muted)]">{rate.method_description}</span>
                                                            )}
                                                            {rate.estimated_days && (
                                                                <span className="block text-xs text-[var(--store-muted)]">{rate.estimated_days} business days</span>
                                                            )}
                                                        </span>
                                                        <span
                                                            className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-bold ${
                                                                rate.is_free
                                                                    ? 'bg-[var(--store-success-soft)] text-[var(--store-success)]'
                                                                    : 'bg-[var(--store-card-hover)] text-[var(--store-text)]'
                                                            }`}
                                                        >
                                                            {rate.is_free ? 'Free' : formatPrice(rate.shipping_cost)}
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    )}
                                </section>
                            )}

                            {/* Payment Method */}
                            <section className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-card)] p-5 shadow-sm md:p-6">
                                <SectionHeading icon={CreditCard} title="Payment Method" subtitle="Choose how you'd like to pay" />
                                {paymentMethods.length === 0 ? (
                                    <div className="space-y-2.5">
                                        {[0, 1].map((i) => (
                                            <div key={i} className="h-[68px] animate-pulse rounded-xl bg-[var(--store-card-hover)]" />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="space-y-2.5">
                                        {paymentMethods.map((method) => {
                                            const active = form.payment_method === method.value;
                                            const MethodIcon = method.value === 'cod' ? Banknote : CreditCard;

                                            return (
                                                <label
                                                    key={method.value}
                                                    className={`flex cursor-pointer items-center gap-3 rounded-xl border-2 px-4 py-3.5 transition ${
                                                        active
                                                            ? 'border-[var(--store-accent)] bg-[var(--store-accent)]/[0.04] shadow-sm'
                                                            : 'border-[var(--store-border)] hover:border-[var(--store-muted)]/40'
                                                    }`}
                                                >
                                                    <span
                                                        className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition ${
                                                            active ? 'border-[var(--store-accent)]' : 'border-[var(--store-border)]'
                                                        }`}
                                                    >
                                                        {active && <span className="h-2.5 w-2.5 rounded-full bg-[var(--store-accent)]" />}
                                                    </span>
                                                    <input
                                                        type="radio"
                                                        name="payment_method"
                                                        value={method.value}
                                                        checked={active}
                                                        onChange={(e) => setField('payment_method', e.target.value)}
                                                        className="sr-only"
                                                    />
                                                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${active ? 'bg-[var(--store-accent)]/10 text-[var(--store-accent)]' : 'bg-[var(--store-card-hover)] text-[var(--store-muted)]'}`}>
                                                        <MethodIcon className="h-5 w-5" />
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block truncate text-sm font-semibold">{method.label}</span>
                                                        <span className="block truncate text-xs text-[var(--store-muted)]">
                                                            {method.value === 'cod' ? 'Pay in cash when your order arrives' : 'Pay securely online now'}
                                                        </span>
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                )}
                                {fieldErrors.payment_method && (
                                    <p className="mt-2 text-xs text-red-500">{fieldErrors.payment_method}</p>
                                )}
                            </section>

                            {/* Notes */}
                            <section className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-card)] p-5 shadow-sm md:p-6">
                                <SectionHeading icon={NotebookPen} title="Order Notes" subtitle="Optional — anything we should know for delivery?" />
                                <textarea
                                    rows={2}
                                    value={form.notes}
                                    onChange={(e) => setField('notes', e.target.value)}
                                    className={inputClass}
                                    placeholder="Any special instructions for delivery..."
                                />
                            </section>
                        </div>

                        {/* Order Summary */}
                        <div className="lg:col-span-1">
                            <div className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-card)] p-5 shadow-sm lg:sticky lg:top-24 md:p-6">
                                <div className="mb-4 flex items-center gap-3">
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--store-accent)]/10 text-[var(--store-accent)]">
                                        <Package className="h-5 w-5" />
                                    </span>
                                    <div>
                                        <h2 className="text-base font-semibold">Order Summary</h2>
                                        <p className="text-xs text-[var(--store-muted)]">
                                            {cart.item_count} {cart.item_count === 1 ? 'item' : 'items'}
                                        </p>
                                    </div>
                                </div>

                                <div className="space-y-3 text-sm">
                                    <div className="max-h-64 space-y-3 overflow-y-auto pr-1">
                                        {cart.items.map((item) => (
                                            <div key={item.id} className="flex items-center gap-3">
                                                <div className="relative h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-[var(--store-card-hover)]">
                                                    <ProductImage src={item.image} seed={item.product.name} alt={item.product.name} />
                                                    {item.quantity > 1 && (
                                                        <span className="absolute right-0 bottom-0 rounded-tl-md bg-[var(--store-text)] px-1.5 text-[10px] font-bold text-[var(--store-bg)]">
                                                            ×{item.quantity}
                                                        </span>
                                                    )}
                                                </div>
                                                <span className="min-w-0 flex-1 truncate text-[var(--store-muted)]">
                                                    {item.product.name}
                                                </span>
                                                <span className="shrink-0 font-medium">{formatPrice(item.line_total)}</span>
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

                                    <div className="border-t border-[var(--store-border)] pt-3">
                                        <div className="flex justify-between text-base font-bold">
                                            <span>Total</span>
                                            <span className="text-lg">{formatPrice(cart.total + (selectedRateId ? (shippingRates.find((r) => r.id === selectedRateId)?.shipping_cost ?? 0) : 0))}</span>
                                        </div>
                                        <p className="mt-1 text-[11px] text-[var(--store-muted)]">Shipping calculated at the rate you selected.</p>
                                    </div>
                                </div>

                                <StoreButton type="submit" disabled={submitting} className="mt-5 w-full">
                                    {submitting ? (
                                        <span className="inline-flex items-center gap-2">
                                            <LoaderCircle className="h-4 w-4 animate-spin" />
                                            Placing order...
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-2">
                                            <Lock className="h-4 w-4" />
                                            Place Order
                                        </span>
                                    )}
                                </StoreButton>

                                <p className="mt-3 flex items-center justify-center gap-1.5 text-[11px] text-[var(--store-muted)]">
                                    <ShieldCheck className="h-3.5 w-3.5" />
                                    Protected by secure checkout
                                </p>

                                <Link
                                    href="/cart"
                                    className="mt-2 flex items-center justify-center gap-1 text-center text-sm text-[var(--store-muted)] hover:underline"
                                >
                                    <ShoppingBag className="h-3.5 w-3.5" />
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
