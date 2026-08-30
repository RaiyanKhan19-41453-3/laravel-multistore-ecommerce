import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { apiStore, clearAuth, getUser, type StoreUser } from '@/lib/auth';
import type { CartSummary } from '@/types';
import { LogOut, ShoppingCart, Store } from 'lucide-react';

export default function StoreLayout({
    title,
    children,
}: {
    title?: string;
    children: ReactNode;
}) {
    const [user, setUser] = useState<StoreUser | null>(null);
    const [cartCount, setCartCount] = useState(0);

    useEffect(() => {
        setUser(getUser());

        void apiStore<CartSummary>('/cart').then((res) => {
            if (res.ok && res.data) {
                setCartCount(res.data.item_count);
            }
        });
    }, []);

    const logout = () => {
        void apiStore('/auth/logout', { method: 'POST' }).finally(() => {
            clearAuth();
            window.location.href = '/';
        });
    };

    return (
        <div className="storefront flex min-h-screen flex-col bg-[var(--store-bg)] text-[var(--store-text)]">
            <Head title={title ?? 'Store'} />

            <header className="border-b border-[var(--store-border)]">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
                    <Link href="/" className="flex items-center gap-2 text-lg font-semibold">
                        <Store className="h-5 w-5" />
                        <span>Store</span>
                    </Link>

                    <nav className="flex items-center gap-6 text-sm">
                        <Link href="/" className="hover:underline">
                            Home
                        </Link>
                        <Link href="/products" className="hover:underline">
                            Products
                        </Link>
                        <Link href="/cart" className="relative flex items-center gap-1 hover:underline">
                            <ShoppingCart className="h-4 w-4" />
                            Cart
                            {cartCount > 0 && (
                                <span className="absolute -top-2 -right-3 flex h-4 w-4 items-center justify-center rounded-full bg-[var(--store-accent)] text-[10px] font-bold text-white">
                                    {cartCount}
                                </span>
                            )}
                        </Link>

                        {user ? (
                            <>
                                <Link href="/orders" className="hover:underline">
                                    Orders
                                </Link>
                                <span className="text-[var(--store-muted)]">{user.name}</span>
                                <button type="button" onClick={logout} className="flex items-center gap-1 hover:underline">
                                    <LogOut className="h-4 w-4" />
                                    Logout
                                </button>
                            </>
                        ) : (
                            <>
                                <Link href="/account/login" className="hover:underline">
                                    Login
                                </Link>
                                <Link
                                    href="/account/register"
                                    className="rounded-md bg-[var(--store-accent)] px-3 py-1.5 text-white hover:opacity-90"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-[var(--store-border)]">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-6 text-sm text-[var(--store-muted)]">
                    <p>&copy; {new Date().getFullYear()} Store. All rights reserved.</p>
                    <div className="flex gap-4">
                        <a href="#" className="hover:underline">
                            Terms
                        </a>
                        <a href="#" className="hover:underline">
                            Privacy
                        </a>
                    </div>
                </div>
            </footer>
        </div>
    );
}
