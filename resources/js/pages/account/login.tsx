import StoreLayout from '@/layouts/store-layout';
import { apiStore, setAuth, type StoreUser } from '@/lib/auth';
import { getGuestToken } from '@/lib/guest-token';
import { useT } from '@/lib/store';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function StoreLogin() {
    const t = useT();
    const [identifier, setIdentifier] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const params = new URLSearchParams(window.location.search);
    const requestedRedirect = params.get('redirect') || '/';
    const redirectTo = requestedRedirect.startsWith('/') && !requestedRedirect.startsWith('//') ? requestedRedirect : '/';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);

        void apiStore<{ user: StoreUser; token: string }>('/auth/login', {
            body: { identifier, password },
        }).then((res) => {
            if (res.ok && res.data) {
                const guestToken = getGuestToken();
                setAuth(res.data.user);

                void apiStore('/cart/merge', {
                    body: { guest_token: guestToken },
                }).finally(() => {
                    setBusy(false);
                    router.visit(redirectTo);
                });
            } else {
                setBusy(false);
                setError(res.message ?? 'Invalid credentials.');
            }
        });
    };

    return (
        <StoreLayout title={t('store.login')}>
            <div className="mx-auto max-w-md px-4 py-12 md:py-16">
                <div className="rounded-xl border border-[var(--store-border)] bg-[var(--store-card)] p-6 shadow-[var(--store-shadow)] md:p-8">
                    <h1 className="text-center text-2xl font-bold tracking-tight">{t('store.sign_in_title')}</h1>
                    <p className="mt-2 text-center text-sm text-[var(--store-muted)]">{t('store.sign_in_subtitle')}</p>

                    {error && (
                        <div className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                            {error}
                        </div>
                    )}

                    <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                        <div>
                            <label htmlFor="identifier" className="mb-1.5 block text-sm font-semibold">
                                Email or mobile number
                            </label>
                            <input
                                id="identifier"
                                type="text"
                                required
                                value={identifier}
                                onChange={(e) => setIdentifier(e.target.value)}
                                className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                placeholder="you@example.com or 01XXXXXXXXX"
                            />
                        </div>

                        <div>
                            <label htmlFor="password" className="mb-1.5 block text-sm font-semibold">
                                Password
                            </label>
                            <input
                                id="password"
                                type="password"
                                required
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                placeholder="Password"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={busy}
                            className="rounded-lg bg-[var(--store-accent)] px-4 py-3 text-sm font-bold text-[var(--store-accent-ink)] transition hover:-translate-y-0.5 hover:opacity-90 disabled:translate-none disabled:opacity-50"
                        >
                            {busy ? 'Logging in...' : t('store.login')}
                        </button>
                    </form>

                    <p className="mt-6 text-center text-sm text-[var(--store-muted)]">
                        Don&apos;t have an account?{' '}
                        <Link href="/account/register" className="font-semibold text-[var(--store-accent)] hover:underline">
                            {t('store.register')}
                        </Link>
                    </p>

                    <p className="mt-2 text-center text-sm text-[var(--store-muted)]">
                        <Link href="/account/forgot-password" className="font-medium text-[var(--store-accent)] hover:underline">
                            Forgot your password?
                        </Link>
                    </p>
                </div>
            </div>
        </StoreLayout>
    );
}
