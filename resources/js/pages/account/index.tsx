import StoreLayout from '@/layouts/store-layout';
import type { StoreUser } from '@/lib/auth';
import { getUser } from '@/lib/auth';
import { useT } from '@/lib/store';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function AccountIndex() {
    const t = useT();
    const [user, setUser] = useState<StoreUser | null>(null);

    useEffect(() => {
        const current = getUser();
        if (!current) {
            router.visit('/account/login?redirect=/account');
            return;
        }
        setUser(current);
    }, []);

    if (!user) {
        return (
            <StoreLayout title={t('store.account')}>
                <div className="mx-auto max-w-3xl px-4 py-12 text-[var(--store-muted)]">{t('store.loading')}</div>
            </StoreLayout>
        );
    }

    return (
        <StoreLayout title={t('store.account')}>
            <div className="mx-auto max-w-3xl px-4 py-8">
                <h1 className="mb-6 text-2xl font-bold">{t('store.account')}</h1>

                <div className="mb-6 rounded-lg border border-[var(--store-border)] p-5">
                    <h2 className="mb-1 text-sm text-[var(--store-muted)]">{t('store.profile')}</h2>
                    <p className="font-medium">{user.name}</p>
                    <p className="text-sm text-[var(--store-muted)]">{user.email}</p>
                    {user.phone && <p className="text-sm text-[var(--store-muted)]">{user.phone}</p>}
                    <Link href="/account/forgot-password" className="mt-3 inline-block text-sm text-[var(--store-accent)] hover:underline">
                        {t('store.reset_password')}
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Link href="/account/orders" className="rounded-lg border border-[var(--store-border)] p-5 transition hover:shadow-md">
                        <h2 className="font-semibold">{t('store.my_orders')}</h2>
                        <p className="mt-1 text-sm text-[var(--store-muted)]">{t('store.order_history')}</p>
                    </Link>
                    <Link href="/account/addresses" className="rounded-lg border border-[var(--store-border)] p-5 transition hover:shadow-md">
                        <h2 className="font-semibold">{t('store.addresses')}</h2>
                        <p className="mt-1 text-sm text-[var(--store-muted)]">{t('store.no_addresses')}</p>
                    </Link>
                    <Link href="/wishlist" className="rounded-lg border border-[var(--store-border)] p-5 transition hover:shadow-md">
                        <h2 className="font-semibold">{t('store.wishlist')}</h2>
                        <p className="mt-1 text-sm text-[var(--store-muted)]">{t('store.wishlist_empty')}</p>
                    </Link>
                </div>
            </div>
        </StoreLayout>
    );
}
