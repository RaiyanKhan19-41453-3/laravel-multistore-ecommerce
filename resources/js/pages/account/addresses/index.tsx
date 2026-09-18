import StoreLayout from '@/layouts/store-layout';
import { apiStore, getUser } from '@/lib/auth';
import { useT } from '@/lib/store';
import type { AccountAddress } from '@/types';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const EMPTY_FORM = {
    name: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    is_default: false,
};

export default function AccountAddressesIndex() {
    const t = useT();
    const [addresses, setAddresses] = useState<AccountAddress[]>([]);
    const [loading, setLoading] = useState(true);
    const [editingId, setEditingId] = useState<number | 'new' | null>(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [saving, setSaving] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});
    const [notice, setNotice] = useState<string | null>(null);

    const load = () => {
        setLoading(true);
        void apiStore<AccountAddress[]>('/addresses')
            .then((res) => {
                if (res.ok && res.data) {
                    setAddresses(res.data);
                } else if (!getUser()) {
                    router.visit('/account/login?redirect=/account/addresses');
                }
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        if (!getUser()) {
            router.visit('/account/login?redirect=/account/addresses');
            return;
        }
        load();
    }, []);

    const startNew = () => {
        setEditingId('new');
        setForm(EMPTY_FORM);
        setFormErrors({});
    };

    const startEdit = (address: AccountAddress) => {
        setEditingId(address.id);
        setForm({
            name: address.name,
            phone: address.phone,
            address: address.address,
            city: address.city,
            state: address.state,
            postal_code: address.postal_code ?? '',
            country: address.country ?? '',
            is_default: address.is_default,
        });
        setFormErrors({});
    };

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setFormErrors({});
        setNotice(null);

        const request =
            editingId === 'new' ? apiStore('/addresses', { body: form }) : apiStore(`/addresses/${editingId}`, { method: 'PATCH', body: form });

        void request
            .then((res) => {
                setSaving(false);
                if (res.ok) {
                    setEditingId(null);
                    load();
                } else {
                    setFormErrors(res.errors ?? {});
                    setNotice(res.message ?? t('store.error_loading'));
                }
            })
            .catch(() => {
                setSaving(false);
                setNotice(t('store.error_loading'));
            });
    };

    const remove = (id: number) => {
        if (!confirm(`${t('store.delete')}?`)) return;
        void apiStore(`/addresses/${id}`, { method: 'DELETE' }).then((res) => {
            if (res.ok) {
                setAddresses((prev) => prev.filter((a) => a.id !== id));
            }
        });
    };

    const field = (key: keyof typeof EMPTY_FORM, label: string, required = true) => (
        <div>
            <label className="mb-1 block text-sm font-medium">{label}</label>
            <input
                type="text"
                required={required}
                value={String(form[key])}
                onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
            />
            {formErrors[key] && <p className="mt-1 text-xs text-red-500">{formErrors[key][0]}</p>}
        </div>
    );

    return (
        <StoreLayout title={t('store.addresses')}>
            <div className="mx-auto max-w-3xl px-4 py-8">
                <nav className="mb-4 text-sm text-[var(--store-muted)]">
                    <Link href="/account" className="hover:underline">
                        {t('store.account')}
                    </Link>
                    <span className="mx-1">/</span>
                    <span className="text-[var(--store-text)]">{t('store.addresses')}</span>
                </nav>

                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">{t('store.addresses')}</h1>
                    {editingId === null && (
                        <button
                            type="button"
                            onClick={startNew}
                            className="rounded-md bg-[var(--store-accent)] px-3 py-1.5 text-sm text-white hover:opacity-90"
                        >
                            {t('store.add_address')}
                        </button>
                    )}
                </div>

                {loading ? (
                    <div className="animate-pulse space-y-3" aria-label={t('store.loading')}>
                        {[0, 1].map((i) => (
                            <div key={i} className="h-24 rounded-lg bg-gray-200 dark:bg-neutral-700" />
                        ))}
                    </div>
                ) : (
                    <div className="space-y-4">
                        {editingId !== null && (
                            <form onSubmit={save} className="space-y-3 rounded-lg border border-[var(--store-border)] p-5">
                                <h2 className="font-semibold">{editingId === 'new' ? t('store.add_address') : t('store.edit_address')}</h2>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {field('name', 'Name')}
                                    {field('phone', 'Phone')}
                                </div>
                                {field('address', 'Address')}
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {field('city', 'City')}
                                    {field('state', 'State')}
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {field('postal_code', 'Postal code', false)}
                                    {field('country', 'Country', false)}
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.is_default}
                                        onChange={(e) => setForm({ ...form, is_default: e.target.checked })}
                                        className="rounded"
                                    />
                                    {t('store.set_default')}
                                </label>
                                {notice && <p className="text-sm text-red-500">{notice}</p>}
                                <div className="flex gap-2">
                                    <button
                                        type="submit"
                                        disabled={saving}
                                        className="rounded-md bg-[var(--store-accent)] px-4 py-2 text-sm text-white hover:opacity-90 disabled:opacity-50"
                                    >
                                        {saving ? '...' : editingId === 'new' ? t('store.add_address') : t('store.save')}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setEditingId(null)}
                                        className="rounded-md border border-[var(--store-border)] px-4 py-2 text-sm"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </form>
                        )}

                        {addresses.length === 0 && editingId === null ? (
                            <p className="py-8 text-center text-[var(--store-muted)]">{t('store.no_addresses')}</p>
                        ) : (
                            addresses.map((address) => (
                                <div key={address.id} className="rounded-lg border border-[var(--store-border)] p-4">
                                    <div className="flex items-center justify-between">
                                        <p className="font-medium">{address.name}</p>
                                        {address.is_default && (
                                            <span className="rounded-lg bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900 dark:text-green-300">
                                                {t('store.default_badge')}
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-1 text-sm text-[var(--store-muted)]">
                                        {address.address}, {address.city}
                                        {address.state ? `, ${address.state}` : ''}
                                        {address.postal_code ? ` ${address.postal_code}` : ''}
                                        {address.country ? `, ${address.country}` : ''}
                                    </p>
                                    <p className="text-sm text-[var(--store-muted)]">{address.phone}</p>
                                    <div className="mt-3 flex gap-3 text-sm">
                                        <button
                                            type="button"
                                            onClick={() => startEdit(address)}
                                            className="text-[var(--store-accent)] hover:underline"
                                        >
                                            {t('store.edit_address')}
                                        </button>
                                        <button type="button" onClick={() => remove(address.id)} className="text-red-500 hover:underline">
                                            ✕
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                )}
            </div>
        </StoreLayout>
    );
}
