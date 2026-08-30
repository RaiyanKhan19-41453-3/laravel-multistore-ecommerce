import StoreLayout from '@/layouts/store-layout';
import { apiStore, setAuth, type StoreUser } from '@/lib/auth';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function StoreRegister() {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);

        void apiStore<{ user: StoreUser; token: string }>('/auth/register', {
            body: { name, email, phone, password, password_confirmation: passwordConfirmation },
        }).then((res) => {
            setBusy(false);

            if (res.ok && res.data) {
                setAuth(res.data.token, res.data.user);
                router.visit('/');
            } else {
                setError(res.message ?? 'Registration failed.');
            }
        });
    };

    return (
        <StoreLayout title="Register">
            <div className="mx-auto max-w-md px-4 py-12">
                <h1 className="mb-6 text-center text-2xl font-bold">Create an account</h1>

                {error && (
                    <div className="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
                )}

                <form onSubmit={submit} className="flex flex-col gap-4">
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
                            className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                            placeholder="Your name"
                        />
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
                            className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                            placeholder="you@example.com"
                        />
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
                            className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                            placeholder="01XXXXXXXXX"
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
                            placeholder="Min 8 characters"
                        />
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
                            className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                            placeholder="Repeat password"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={busy}
                        className="rounded-lg bg-[var(--store-accent)] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
                    >
                        {busy ? 'Creating account...' : 'Register'}
                    </button>
                </form>

                <p className="mt-6 text-center text-sm text-[var(--store-muted)]">
                    Already have an account?{' '}
                    <Link href="/account/login" className="text-[var(--store-accent)] hover:underline">
                        Login
                    </Link>
                </p>
            </div>
        </StoreLayout>
    );
}
