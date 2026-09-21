import StoreLayout from '@/layouts/store-layout';
import StoreButton from '@/components/store/store-button';
import { apiStore } from '@/lib/auth';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function ResetPassword() {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token') ?? '';
    const emailParam = params.get('email') ?? '';
    const phoneParam = params.get('phone') ?? '';

    const [email] = useState(emailParam);
    const [phone] = useState(phoneParam);
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    if (!token) {
        return (
            <StoreLayout title="Reset Password">
                <div className="mx-auto max-w-md px-4 py-12 text-center">
                    <h1 className="mb-4 text-2xl font-bold">Invalid Reset Link</h1>
                    <p className="mb-6 text-sm text-[var(--store-muted)]">
                        This password reset link is invalid or has expired.
                    </p>
                    <StoreButton href="/account/forgot-password">
                        Request a new link
                    </StoreButton>
                </div>
            </StoreLayout>
        );
    }

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        setSuccess(null);

        const body: Record<string, string> = {
            token,
            password,
            password_confirmation: passwordConfirmation,
        };

        if (email) body.email = email;
        if (phone) body.phone = phone;

        void apiStore('/auth/reset-password', { body }).then((res) => {
            setBusy(false);

            if (res.ok) {
                setSuccess('Password reset successfully. Redirecting to login...');
                setTimeout(() => router.visit('/account/login'), 2000);
            } else {
                setError(res.message ?? 'Invalid or expired reset token.');
            }
        });
    };

    return (
        <StoreLayout title="Reset Password">
            <div className="mx-auto max-w-md px-4 py-12">
                <h1 className="mb-6 text-center text-2xl font-bold">Reset Password</h1>

                {error && (
                    <div className="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
                )}

                {success && (
                    <div className="mb-4 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-700">{success}</div>
                )}

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div>
                        <label htmlFor="password" className="mb-1 block text-sm font-medium">
                            New Password
                        </label>
                        <input
                            id="password"
                            type="password"
                            required
                            minLength={8}
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                            placeholder="At least 8 characters"
                        />
                    </div>

                    <div>
                        <label htmlFor="password_confirmation" className="mb-1 block text-sm font-medium">
                            Confirm Password
                        </label>
                        <input
                            id="password_confirmation"
                            type="password"
                            required
                            minLength={8}
                            value={passwordConfirmation}
                            onChange={(e) => setPasswordConfirmation(e.target.value)}
                            className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                            placeholder="Repeat password"
                        />
                    </div>

                    <StoreButton type="submit" disabled={busy} className="w-full">
                        {busy ? 'Resetting...' : 'Reset Password'}
                    </StoreButton>
                </form>

                <p className="mt-6 text-center text-sm text-[var(--store-muted)]">
                    Remember your password?{' '}
                    <Link href="/account/login" className="text-[var(--store-accent)] hover:underline">
                        Login
                    </Link>
                </p>
            </div>
        </StoreLayout>
    );
}
