import StoreLayout from '@/layouts/store-layout';
import { apiStore } from '@/lib/auth';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Step = 'identifier' | 'otp' | 'reset' | 'done';

export default function ForgotPassword() {
    const [step, setStep] = useState<Step>('identifier');
    const [identifier, setIdentifier] = useState('');
    const [otp, setOtp] = useState('');
    const [phone, setPhone] = useState('');
    const [resetToken, setResetToken] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const trimmedIdentifier = identifier.trim();
    const isEmail = /.+@.+\..+/.test(trimmedIdentifier);

    const submitIdentifier = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);

        void apiStore('/auth/forgot-password', {
            body: { identifier: trimmedIdentifier },
        }).then((res) => {
            setBusy(false);

            if (res.ok) {
                setSuccess(res.message ?? 'Check your inbox or phone for the reset instructions.');
                if (!isEmail) {
                    setPhone(trimmedIdentifier);
                    setStep('otp');
                }
            } else {
                setError(res.message ?? 'Something went wrong. Please try again.');
            }
        });
    };

    const submitOtp = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);

        void apiStore<{ reset_token: string; phone: string }>('/auth/verify-otp', {
            body: { phone, otp },
        }).then((res) => {
            setBusy(false);

            if (res.ok && res.data) {
                setResetToken(res.data.reset_token);
                setPhone(res.data.phone);
                setStep('reset');
            } else {
                setError(res.message ?? 'Invalid or expired OTP.');
            }
        });
    };

    const submitReset = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);

        void apiStore('/auth/reset-password', {
            body: {
                token: resetToken,
                phone,
                password,
                password_confirmation: passwordConfirmation,
            },
        }).then((res) => {
            setBusy(false);

            if (res.ok) {
                setStep('done');
                setSuccess('Password reset successfully. Redirecting to login...');
                setTimeout(() => router.visit('/account/login'), 2000);
            } else {
                setError(res.message ?? 'Invalid or expired reset token.');
            }
        });
    };

    return (
        <StoreLayout title="Forgot Password">
            <div className="mx-auto max-w-md px-4 py-12">
                {step === 'identifier' && (
                    <>
                        <h1 className="mb-2 text-center text-2xl font-bold">Forgot Password</h1>
                        <p className="mb-6 text-center text-sm text-[var(--store-muted)]">
                            Enter your email or phone number and we&apos;ll help you reset your password.
                        </p>

                        {error && (
                            <div className="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
                        )}

                        {success && (
                            <div className="mb-4 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-700">{success}</div>
                        )}

                        <form onSubmit={submitIdentifier} className="flex flex-col gap-4">
                            <div>
                                <label htmlFor="identifier" className="mb-1 block text-sm font-medium">
                                    Email or phone number
                                </label>
                                <input
                                    id="identifier"
                                    type="text"
                                    required
                                    value={identifier}
                                    onChange={(e) => { setIdentifier(e.target.value); setSuccess(null); setError(null); }}
                                    className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                                    placeholder="you@example.com or 01XXXXXXXXX"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={busy}
                                className="rounded-lg bg-[var(--store-accent)] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
                            >
                                {busy ? 'Sending...' : 'Continue'}
                            </button>
                        </form>
                    </>
                )}

                {step === 'otp' && (
                    <>
                        <h1 className="mb-2 text-center text-2xl font-bold">Enter Verification Code</h1>
                        <p className="mb-6 text-center text-sm text-[var(--store-muted)]">
                            We sent a 6-digit code to <span className="font-medium">{phone}</span>
                        </p>

                        {error && (
                            <div className="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
                        )}

                        <form onSubmit={submitOtp} className="flex flex-col gap-4">
                            <div>
                                <label htmlFor="otp" className="mb-1 block text-sm font-medium">
                                    Verification code
                                </label>
                                <input
                                    id="otp"
                                    type="text"
                                    required
                                    maxLength={6}
                                    value={otp}
                                    onChange={(e) => setOtp(e.target.value.replace(/\D/g, ''))}
                                    className="w-full rounded-md border border-[var(--store-border)] px-3 py-2 text-sm tracking-widest"
                                    placeholder="000000"
                                    autoFocus
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={busy || otp.length !== 6}
                                className="rounded-lg bg-[var(--store-accent)] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
                            >
                                {busy ? 'Verifying...' : 'Verify Code'}
                            </button>
                        </form>

                        <p className="mt-6 text-center text-sm text-[var(--store-muted)]">
                            <button
                                type="button"
                                onClick={() => { setStep('identifier'); setSuccess(null); setError(null); }}
                                className="text-[var(--store-accent)] hover:underline"
                            >
                                Use a different method
                            </button>
                        </p>
                    </>
                )}

                {step === 'reset' && (
                    <>
                        <h1 className="mb-6 text-center text-2xl font-bold">Reset Password</h1>

                        {error && (
                            <div className="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
                        )}

                        <form onSubmit={submitReset} className="flex flex-col gap-4">
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

                            <button
                                type="submit"
                                disabled={busy}
                                className="rounded-lg bg-[var(--store-accent)] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
                            >
                                {busy ? 'Resetting...' : 'Reset Password'}
                            </button>
                        </form>
                    </>
                )}

                {step === 'done' && success && (
                    <div className="text-center">
                        <h1 className="mb-4 text-2xl font-bold">Password Reset</h1>
                        <div className="mb-4 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-700">{success}</div>
                    </div>
                )}

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
