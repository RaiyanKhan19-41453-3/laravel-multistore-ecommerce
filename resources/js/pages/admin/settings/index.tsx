import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface SettingsPageProps {
    settings: Record<string, string | null>;
    presets: Record<string, Record<string, string>>;
    currencies: string[];
    taxModes: string[];
    locales: string[];
    payments: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Settings', href: '/admin/settings' },
];

const TIMEZONES = ['Asia/Dhaka', 'Asia/Riyadh', 'Asia/Dubai', 'UTC'];

type SettingsGroup = Record<string, string>;

type SettingsFormData = {
    store: SettingsGroup;
    tax: SettingsGroup;
    payment: SettingsGroup;
    notifications: SettingsGroup;
};

type SettingsGroupKey = keyof SettingsFormData;

export default function AdminSettings({ settings, presets, currencies, taxModes, locales, payments }: SettingsPageProps) {
    const { data, setData, put, errors, processing, recentlySuccessful } = useForm<SettingsFormData>({
        store: {
            name: settings['store.name'] ?? '',
            country: settings['store.country'] ?? 'BD',
            currency: settings['store.currency'] ?? 'BDT',
            locale: settings['store.locale'] ?? 'en',
            timezone: settings['store.timezone'] ?? 'Asia/Dhaka',
        },
        tax: {
            mode: settings['tax.mode'] ?? 'off',
            rate: settings['tax.rate'] ?? '0',
        },
        payment: {
            cod_enabled: settings['payment.cod_enabled'] ?? '1',
            sslcommerz_enabled: settings['payment.sslcommerz_enabled'] ?? '1',
            bkash_enabled: settings['payment.bkash_enabled'] ?? '0',
            moyasar_enabled: settings['payment.moyasar_enabled'] ?? '0',
            tabby_enabled: settings['payment.tabby_enabled'] ?? '0',
            stripe_enabled: settings['payment.stripe_enabled'] ?? '0',
        },
        notifications: {
            mail_enabled: settings['notifications.mail_enabled'] ?? '1',
            sms_enabled: settings['notifications.sms_enabled'] ?? '0',
        },
    });
    const [presetBusy, setPresetBusy] = useState<string | null>(null);

    // Inertia's setData typing only allows top-level keys, but the API
    // expects nested objects (see controllers.md). This helper keeps the
    // dotted-path updates type-safe without per-call casts.
    const setField = (group: SettingsGroupKey, field: string, value: string) => {
        setData((prev) => {
            const next = { ...prev };
            next[group] = { ...next[group], [field]: value };
            return next;
        });
    };

    const formErrors = errors as unknown as Record<string, string | undefined>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update'));
    };

    const applyPreset = (country: string) => {
        setPresetBusy(country);
        router.post(route('admin.settings.preset'), { country }, { onFinish: () => setPresetBusy(null) });
    };

    const selectClass =
        'w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Store Settings" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Store Settings"
                    description="One codebase, any client. Pick a country preset, then tweak currency, language, and tax."
                />

                <section className="rounded-xl border p-5">
                    <h2 className="mb-1 text-base font-semibold">Country preset</h2>
                    <p className="text-muted-foreground mb-4 text-sm">
                        BD starts with BDT, English, and tax off. SA starts with SAR, Arabic, and 15% VAT.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        {Object.keys(presets).map((country) => (
                            <Button key={country} type="button" variant="outline" disabled={presetBusy !== null} onClick={() => applyPreset(country)}>
                                {presetBusy === country ? 'Applying...' : `Apply ${country} preset`}
                            </Button>
                        ))}
                    </div>
                    <div className="text-muted-foreground mt-3 grid gap-2 text-xs md:grid-cols-2">
                        {Object.entries(presets).map(([country, values]) => (
                            <p key={country}>
                                <span className="font-semibold">{country}:</span> {Object.values(values).join(' · ')}
                            </p>
                        ))}
                    </div>
                </section>

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-4 rounded-xl border p-5">
                        <h2 className="text-base font-semibold">Store</h2>
                        <p className="text-muted-foreground -mt-2 text-sm">
                            Name, logo, tagline, and contact details live under <a href="/admin/store-profile" className="font-medium underline">Store Profile</a>.
                        </p>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="store.country">Country</Label>
                                <select
                                    id="store.country"
                                    className={selectClass}
                                    value={data.store.country}
                                    onChange={(e) => setField('store', 'country', e.target.value)}
                                >
                                    <option value="BD">BD — Bangladesh</option>
                                    <option value="SA">SA — Saudi Arabia</option>
                                    <option value="AE">AE — UAE</option>
                                    <option value="US">US — United States</option>
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="store.currency">Currency</Label>
                                <select
                                    id="store.currency"
                                    className={selectClass}
                                    value={data.store.currency}
                                    onChange={(e) => setField('store', 'currency', e.target.value)}
                                >
                                    {currencies.map((c) => (
                                        <option key={c} value={c}>
                                            {c}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="store.locale">Language</Label>
                                <select
                                    id="store.locale"
                                    className={selectClass}
                                    value={data.store.locale}
                                    onChange={(e) => setField('store', 'locale', e.target.value)}
                                >
                                    {locales.map((l) => (
                                        <option key={l} value={l}>
                                            {l === 'ar' ? 'ar — العربية (RTL)' : 'en — English'}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="store.timezone">Timezone</Label>
                                <select
                                    id="store.timezone"
                                    className={selectClass}
                                    value={data.store.timezone}
                                    onChange={(e) => setField('store', 'timezone', e.target.value)}
                                >
                                    {TIMEZONES.map((tz) => (
                                        <option key={tz} value={tz}>
                                            {tz}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </section>

                    <section className="space-y-4 rounded-xl border p-5">
                        <h2 className="text-base font-semibold">Tax</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="tax.mode">Mode</Label>
                                <select
                                    id="tax.mode"
                                    className={selectClass}
                                    value={data.tax.mode}
                                    onChange={(e) => setField('tax', 'mode', e.target.value)}
                                >
                                    {taxModes.map((m) => (
                                        <option key={m} value={m}>
                                            {m === 'off' ? 'off — no tax' : m.toUpperCase()}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tax.rate">Rate %</Label>
                                <Input
                                    id="tax.rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value={data.tax.rate}
                                    onChange={(e) => setField('tax', 'rate', e.target.value)}
                                />
                                {formErrors['tax.rate'] && <p className="text-sm text-red-500">{formErrors['tax.rate']}</p>}
                            </div>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            ZATCA e-invoicing builds on VAT mode. Turning tax off while ZATCA is enabled keeps VAT on automatically.
                        </p>
                    </section>

                    <section className="space-y-4 rounded-xl border p-5 lg:col-span-2">
                        <h2 className="text-base font-semibold">Payments</h2>
                        <p className="text-muted-foreground text-xs">
                            Toggle per-country gateways. BD: SSLCommerz + bKash + COD. SA: Moyasar (Mada) + Tabby + Stripe + COD. Changes apply
                            instantly to checkout.
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {Object.entries(payments).map(([key, label]) => {
                                const field = `${key}_enabled` as keyof typeof data.payment;
                                const checked =
                                    data.payment[field] === '1' ||
                                    data.payment[field] === 'true' ||
                                    data.payment[field] === (true as unknown as string);

                                return (
                                    <div key={key} className="flex items-center justify-between rounded-lg border px-4 py-3">
                                        <Label htmlFor={`payment.${field}`} className="text-sm font-medium">
                                            {label}
                                        </Label>
                                        <Switch
                                            id={`payment.${field}`}
                                            checked={checked}
                                            onCheckedChange={(v) => setField('payment', field, v ? '1' : '0')}
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    <section className="space-y-4 rounded-xl border p-5 lg:col-span-2">
                        <h2 className="text-base font-semibold">Notifications</h2>
                        <p className="text-muted-foreground text-xs">
                            Order emails plus review and back-in-stock alerts. SMS costs per message and needs a configured gateway, so it stays off
                            until you enable it.
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {(
                                [
                                    ['mail_enabled', 'Order + review emails'],
                                    ['sms_enabled', 'Order SMS updates'],
                                ] as const
                            ).map(([field, label]) => {
                                const checked =
                                    data.notifications[field] === '1' ||
                                    data.notifications[field] === 'true' ||
                                    data.notifications[field] === (true as unknown as string);

                                return (
                                    <div key={field} className="flex items-center justify-between rounded-lg border px-4 py-3">
                                        <Label htmlFor={`notifications.${field}`} className="text-sm font-medium">
                                            {label}
                                        </Label>
                                        <Switch
                                            id={`notifications.${field}`}
                                            checked={checked}
                                            onCheckedChange={(v) => setField('notifications', field, v ? '1' : '0')}
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    <div className="flex items-center gap-3 lg:col-span-2">
                        <Button disabled={processing}>{processing ? 'Saving...' : 'Save settings'}</Button>
                        {recentlySuccessful && <span className="text-sm text-green-600">Saved.</span>}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
