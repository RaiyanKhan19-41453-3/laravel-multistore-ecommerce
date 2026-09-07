import StoreLayout from '@/layouts/store-layout';
import { apiStore, setAuth, type StoreUser } from '@/lib/auth';
import { getGuestToken } from '@/lib/guest-token';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function StoreLogin() {
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
        <StoreLayout title="Login">
            <div className="mx-auto max-w-md px-4 py-12">
                <h1 className="mb-6 text-center text-2xl font-bold">Login</h1>

                {error && (
                    <div className="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
                )}

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div>
                        <label htmlFor="identifier" className="mb-1 block text-sm font-medium">
                            Email or mobile number
                        </label>
                        <input
                            id="identifier"
                            type="text"
                            required
                            value={identifier}
                            onChange={(e) => setIdentifier(e.target.value)}
                            className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                            placeholder="you@example.com or 01XXXXXXXXX"
                        />
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
                            className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                            placeholder="Password"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={busy}
                        className="rounded-lg bg-[var(--store-accent)] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
                    >
                        {busy ? 'Logging in...' : 'Login'}
                    </button>
                </form>

                <p className="mt-6 text-center text-sm text-[var(--store-muted)]">
                    Don&apos;t have an account?{' '}
                    <Link href="/account/register" className="text-[var(--store-accent)] hover:underline">
                        Register
                    </Link>
                </p>

                <p className="mt-2 text-center text-sm text-[var(--store-muted)]">
                    <Link href="/account/forgot-password" className="text-[var(--store-accent)] hover:underline">
                        Forgot your password?
                    </Link>
                </p>
            </div>
        </StoreLayout>
    );
}
