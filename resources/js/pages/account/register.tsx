import StoreLayout from '@/layouts/store-layout';
import StoreButton from '@/components/store/store-button';
import { apiStore, setAuth, type StoreUser } from '@/lib/auth';
import { getGuestToken } from '@/lib/guest-token';
import { useT } from '@/lib/store';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function StoreRegister() {
    const t = useT();
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        setFieldErrors({});

        void apiStore<{ user: StoreUser; token: string }>('/auth/register', {
            body: { name, email, phone, password, password_confirmation: passwordConfirmation },
        }).then((res) => {
            if (res.ok && res.data) {
                const guestToken = getGuestToken();
                setAuth(res.data.user);

                void apiStore('/cart/merge', {
                    body: { guest_token: guestToken },
                }).finally(() => {
                    setBusy(false);
                    router.visit('/');
                });
            } else {
                setBusy(false);
                setError(res.message ?? 'Registration failed.');

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

    return (
        <StoreLayout title={t('store.register')}>
            <div className="mx-auto max-w-md px-4 py-12 md:py-16">
                <div className="rounded-xl border border-[var(--store-border)] bg-[var(--store-card)] p-6 shadow-[var(--store-shadow)] md:p-8">
                    <h1 className="text-center text-2xl font-bold tracking-tight">{t('store.register_title')}</h1>
                    <p className="mt-2 text-center text-sm text-[var(--store-muted)]">{t('store.register_subtitle')}</p>

                    {error && (
                        <div className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                            {error}
                        </div>
                    )}

                    <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                    <div>
                        <label htmlFor="name" className="mb-1 block text-sm font-medium">
                            Name
                        </label>
                        <input
                            id="name"
                            type="text"
                            required
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                            placeholder="Your name"
                        />
                        {fieldErrors.name && <p className="mt-1 text-xs text-red-500">{fieldErrors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="email" className="mb-1 block text-sm font-medium">
                            Email
                        </label>
                        <input
                            id="email"
                            type="email"
                            required
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                            placeholder="you@example.com"
                        />
                        {fieldErrors.email && <p className="mt-1 text-xs text-red-500">{fieldErrors.email}</p>}
                    </div>

                    <div>
                        <label htmlFor="phone" className="mb-1 block text-sm font-medium">
                            Mobile number
                        </label>
                        <input
                            id="phone"
                            type="tel"
                            required
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                            placeholder="01XXXXXXXXX"
                        />
                        {fieldErrors.phone && <p className="mt-1 text-xs text-red-500">{fieldErrors.phone}</p>}
                    </div>

                    <div>
                        <label htmlFor="password" className="mb-1 block text-sm font-medium">
                            Password
                        </label>
                        <input
                            id="password"
                            type="password"
                            required
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                            placeholder="Min 8 characters"
                        />
                        {fieldErrors.password && <p className="mt-1 text-xs text-red-500">{fieldErrors.password}</p>}
                    </div>

                    <div>
                        <label htmlFor="password_confirmation" className="mb-1 block text-sm font-medium">
                            Confirm password
                        </label>
                        <input
                            id="password_confirmation"
                            type="password"
                            required
                            value={passwordConfirmation}
                            onChange={(e) => setPasswordConfirmation(e.target.value)}
                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                            placeholder="Repeat password"
                        />
                    </div>

                    <StoreButton type="submit" disabled={busy} className="w-full">
                        {busy ? 'Creating account...' : t('store.register')}
                    </StoreButton>
                </form>

                <p className="mt-6 text-center text-sm text-[var(--store-muted)]">
                    Already have an account?{' '}
                    <Link href="/account/login" className="font-semibold text-[var(--store-accent)] hover:underline">
                        {t('store.login')}
                    </Link>
                </p>
                </div>
            </div>
        </StoreLayout>
    );
}
