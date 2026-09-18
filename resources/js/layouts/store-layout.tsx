import { apiStore, clearAuth, getUser, type StoreUser } from '@/lib/auth';
import { useDirection, useLocale, useStore, useT } from '@/lib/store';
import type { CartSummary } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Heart, LogOut, Mail, MapPin, Menu, Package, Phone, ShoppingBag, Sparkles, User as UserIcon, X, Banknote } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import MiniCart, { openMiniCart } from '@/components/store/mini-cart';
import SearchBox from '@/components/store/search-box';
import StoreLogo from '@/components/store/store-logo';
import { CategoryMenu, type NavCategory } from '@/components/store/category-nav';
import { DesktopNav, MobileNav, type MenuNode } from '@/components/store/site-nav';

const NAV_LINKS = [
    { href: '/', key: 'store.home' },
    { href: '/products', key: 'store.products' },
] as const;

export default function StoreLayout({ title, children }: { title?: string; children: ReactNode }) {
    const [user, setUser] = useState<StoreUser | null>(null);
    const [cartCount, setCartCount] = useState(0);
    const [menuOpen, setMenuOpen] = useState(false);
    const [menuItems, setMenuItems] = useState<MenuNode[]>([]);
    const [categories, setCategories] = useState<NavCategory[]>([]);
    const store = useStore();
    const direction = useDirection();
    const locale = useLocale();
    const t = useT();

    useEffect(() => {
        document.documentElement.dir = direction;
        document.documentElement.lang = locale;
        document.documentElement.dataset.currency = store.currency;
        (window as unknown as { __STORE__?: unknown }).__STORE__ = store;
    }, [direction, locale, store]);

    useEffect(() => {
        setUser(getUser());

        const refreshCartCount = () => {
            void apiStore<CartSummary>('/cart').then((res) => {
                if (res.ok && res.data) {
                    setCartCount(res.data.item_count);
                }
            });
        };

        refreshCartCount();
        window.addEventListener('cart:updated', refreshCartCount);

        return () => window.removeEventListener('cart:updated', refreshCartCount);
    }, []);

    useEffect(() => {
        void fetch('/api/menus')
            .then((r) => (r.ok ? r.json() : null))
            .then((json: { success: boolean; data: MenuNode[] } | null) => {
                if (json?.success && Array.isArray(json.data)) setMenuItems(json.data);
            })
            .catch(() => {});

        void fetch('/api/categories')
            .then((r) => (r.ok ? r.json() : null))
            .then((json: { success: boolean; data: NavCategory[] } | null) => {
                if (json?.success && Array.isArray(json.data)) setCategories(json.data.slice(0, 10));
            })
            .catch(() => {});
    }, []);

    const logout = () => {
        void apiStore('/auth/logout', { method: 'POST' }).finally(() => {
            clearAuth();
            window.location.href = '/';
        });
    };

    return (
        <div key={`${locale}-${direction}`} className="storefront flex min-h-screen flex-col bg-[var(--store-bg)] text-[var(--store-text)]">
            <Head title={title ?? store.name} />

            {/* Announcement bar */}
            <div className="bg-[var(--store-accent)] px-4 py-2 text-center text-xs font-semibold tracking-wide text-[var(--store-accent-ink)]">
                <span className="inline-flex items-center gap-1.5">
                    <Sparkles className="h-3.5 w-3.5" />
                    {t('store.promo_text')}
                </span>
            </div>

            {/* Utility strip: hotline + trust left, account shortcuts right */}
            <div className="hidden border-b border-[var(--store-border)] bg-[var(--store-card)] md:block">
                <div className="store-container flex items-center gap-4 py-1.5 text-xs text-[var(--store-muted)]">
                    {store.phone && (
                        <a href={`tel:${store.phone.replace(/\s+/g, '')}`} className="flex items-center gap-1.5 font-semibold transition hover:text-[var(--store-accent)]">
                            <Phone className="h-3.5 w-3.5" />
                            {t('store.hotline')}: <span dir="ltr">{store.phone}</span>
                        </a>
                    )}
                    <span className="flex items-center gap-1.5">
                        <Banknote className="h-3.5 w-3.5" />
                        {t('store.cod_note')}
                    </span>
                    <span className="ms-auto flex items-center gap-4">
                        <Link href="/account/orders" className="transition hover:text-[var(--store-accent)]">
                            {t('store.track_order')}
                        </Link>
                        {user ? (
                            <Link href="/account" className="transition hover:text-[var(--store-accent)]">
                                {user.name}
                            </Link>
                        ) : (
                            <Link href="/account/login" className="transition hover:text-[var(--store-accent)]">
                                {t('store.login')}
                            </Link>
                        )}
                        <span className="rounded border border-[var(--store-border)] px-1.5 py-0.5 font-semibold">{store.currency}</span>
                    </span>
                </div>
            </div>

            <header className="sticky top-0 z-40 border-b border-[var(--store-border)] bg-[var(--store-bg)]/90 backdrop-blur-md">
                <div className="store-container relative grid h-16 grid-cols-[1fr_auto_1fr] items-center gap-3">
                    {/* Left: search */}
                    <div className="flex min-w-0 items-center gap-2">
                        <button
                            type="button"
                            aria-label="Open menu"
                            onClick={() => setMenuOpen(true)}
                            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition hover:bg-[var(--store-card-hover)] lg:hidden"
                        >
                            <Menu className="h-5 w-5" />
                        </button>

                    {/* Big search */}
                    <div className="hidden w-full max-w-md min-w-0 md:block">
                        <SearchBox />
                    </div>
                    </div>

                    {/* Center: logo */}
                    <Link href="/" className="flex items-center justify-self-center gap-2.5" aria-label={store.name}>
                        <StoreLogo size="md" />
                        <span className="store-display hidden text-[22px] leading-none font-bold tracking-tight sm:inline">{store.name}</span>
                    </Link>

                    <div className="flex items-center justify-end gap-1">
                        {user && (
                            <Link
                                href="/wishlist"
                                title={t('store.wishlist')}
                                className="flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-[var(--store-card-hover)]"
                            >
                                <Heart className="h-5 w-5" />
                            </Link>
                        )}
                        <button
                            type="button"
                            onClick={openMiniCart}
                            title={t('store.cart')}
                            className="relative flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-[var(--store-card-hover)]"
                        >
                            <ShoppingBag className="h-5 w-5" />
                            {cartCount > 0 && (
                                <span className="absolute top-0.5 end-0.5 flex h-5 min-w-5 items-center justify-center rounded-lg bg-[var(--store-accent)] px-1 text-[11px] font-bold text-[var(--store-accent-ink)]">
                                    {cartCount}
                                </span>
                            )}
                        </button>
                        {user ? (
                            <div className="flex items-center">
                                <Link
                                    href="/account"
                                    title={user.name}
                                    className="flex h-10 items-center gap-2 rounded-lg px-2 transition hover:bg-[var(--store-card-hover)]"
                                >
                                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--store-accent-soft)] text-sm font-bold text-[var(--store-accent)]">
                                        {user.name.charAt(0).toUpperCase()}
                                    </span>
                                    <span className="hidden max-w-24 truncate text-sm font-medium xl:inline">{user.name}</span>
                                </Link>
                                <button
                                    type="button"
                                    onClick={logout}
                                    title={t('store.logout')}
                                    className="hidden h-10 w-10 items-center justify-center rounded-lg transition hover:bg-[var(--store-card-hover)] lg:flex"
                                >
                                    <LogOut className="h-4.5 w-4.5" />
                                </button>
                            </div>
                        ) : (
                            <Link
                                href="/account/login"
                                className="ms-1 hidden rounded-lg bg-[var(--store-accent)] px-5 py-2.5 text-sm font-semibold text-[var(--store-accent-ink)] transition hover:opacity-85 sm:inline-flex"
                            >
                                {t('store.login')}
                            </Link>
                        )}
                    </div>
                </div>

                {/* Category tier: browse button + menu links + offers */}
                <div className="hidden border-t border-[var(--store-border)] lg:block">
                    <div className="store-container relative flex items-center gap-1 py-2">
                        <CategoryMenu categories={categories} />
                        <span aria-hidden="true" className="mx-2 h-5 w-px bg-[var(--store-border)]" />
                        {menuItems.length > 0 ? (
                            <DesktopNav items={menuItems} />
                        ) : (
                            <nav className="flex items-center gap-1 text-sm font-medium" aria-label="Primary">
                                {NAV_LINKS.map((link) => (
                                    <Link
                                        key={link.href}
                                        href={link.href}
                                        className="rounded-lg px-3.5 py-2 text-[var(--store-muted)] transition hover:bg-[var(--store-card-hover)] hover:text-[var(--store-text)]"
                                    >
                                        {t(link.key)}
                                    </Link>
                                ))}
                            </nav>
                        )}
                        <Link
                            href="/products"
                            className="ms-auto flex shrink-0 items-center gap-1.5 rounded-lg bg-[var(--store-accent-soft)] px-4 py-2 text-sm font-bold text-[var(--store-accent)] transition hover:opacity-85"
                        >
                            <Sparkles className="h-3.5 w-3.5" />
                            {t('store.deals')}
                        </Link>
                    </div>
                </div>

                {/* Mobile search */}
                <div className="border-t border-[var(--store-border)] px-4 py-2 md:hidden">
                    <SearchBox compact />
                </div>
            </header>

            {/* Mobile drawer */}
            {menuOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setMenuOpen(false)} />
                    <div className="absolute top-0 bottom-0 start-0 flex w-72 flex-col bg-[var(--store-bg)] p-5 shadow-xl">
                        <div className="mb-6 flex items-center justify-between">
                            <span className="flex items-center gap-2 font-bold">
                                <StoreLogo size="sm" />
                                {store.name}
                            </span>
                            <button
                                type="button"
                                aria-label="Close menu"
                                onClick={() => setMenuOpen(false)}
                                className="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-[var(--store-card-hover)]"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        {categories.length > 0 && (
                            <div className="mb-2">
                                <p className="mb-1 px-3 text-[11px] font-bold tracking-[0.16em] text-[var(--store-muted)] uppercase">
                                    {t('store.categories')}
                                </p>
                                <MobileNav
                                    items={categories.map(
                                        (c): MenuNode => ({
                                            id: -c.id,
                                            title: c.name,
                                            url: `/categories/${c.slug}`,
                                            click_behavior: 'navigate',
                                            display: 'auto',
                                            has_children: false,
                                            children: [],
                                            promo: null,
                                        }),
                                    )}
                                    onNavigate={() => setMenuOpen(false)}
                                />
                            </div>
                        )}
                        {menuItems.length > 0 ? (
                            <MobileNav items={menuItems} onNavigate={() => setMenuOpen(false)} />
                        ) : (
                            <nav className="flex flex-col gap-1 text-[15px] font-medium">
                                {NAV_LINKS.map((link) => (
                                    <Link
                                        key={link.href}
                                        href={link.href}
                                        onClick={() => setMenuOpen(false)}
                                        className="rounded-xl px-3 py-2.5 transition hover:bg-[var(--store-card-hover)]"
                                    >
                                        {t(link.key)}
                                    </Link>
                                ))}
                            </nav>
                        )}
                        <nav className="mt-1 flex flex-col gap-1 text-[15px] font-medium">
                            <button
                                type="button"
                                onClick={() => {
                                    setMenuOpen(false);
                                    openMiniCart();
                                }}
                                className="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-[15px] font-medium transition hover:bg-[var(--store-card-hover)]"
                            >
                                <ShoppingBag className="h-4 w-4" /> {t('store.cart')}
                            </button>
                            {user && (
                                <Link
                                    href="/wishlist"
                                    onClick={() => setMenuOpen(false)}
                                    className="flex items-center gap-2 rounded-xl px-3 py-2.5 transition hover:bg-[var(--store-card-hover)]"
                                >
                                    <Heart className="h-4 w-4" /> {t('store.wishlist')}
                                </Link>
                            )}
                        </nav>
                        <div className="mt-auto border-t border-[var(--store-border)] pt-4">
                            {user ? (
                                <div className="flex flex-col gap-1">
                                    <Link
                                        href="/account/orders"
                                        onClick={() => setMenuOpen(false)}
                                        className="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition hover:bg-[var(--store-card-hover)]"
                                    >
                                        <Package className="h-4 w-4" /> {t('store.orders')}
                                    </Link>
                                    <Link
                                        href="/account"
                                        onClick={() => setMenuOpen(false)}
                                        className="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition hover:bg-[var(--store-card-hover)]"
                                    >
                                        <UserIcon className="h-4 w-4" /> {user.name}
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={logout}
                                        className="flex items-center gap-2 rounded-xl px-3 py-2.5 text-start text-sm font-medium text-[var(--store-muted)] transition hover:bg-[var(--store-card-hover)]"
                                    >
                                        {t('store.logout')}
                                    </button>
                                </div>
                            ) : (
                                <div className="flex flex-col gap-2">
                                    <Link
                                        href="/account/login"
                                        onClick={() => setMenuOpen(false)}
                                        className="rounded-xl bg-[var(--store-accent)] px-4 py-2.5 text-center text-sm font-semibold text-[var(--store-accent-ink)]"
                                    >
                                        {t('store.login')}
                                    </Link>
                                    <Link
                                        href="/account/register"
                                        onClick={() => setMenuOpen(false)}
                                        className="rounded-xl border border-[var(--store-border)] px-4 py-2.5 text-center text-sm font-semibold"
                                    >
                                        {t('store.register')}
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            <main className="flex-1">{children}</main>
            <MiniCart />

            <footer className="mt-16 border-t border-[var(--store-border)] bg-[var(--store-card)]">
                <div className="store-container grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <span className="flex items-center gap-2 text-base font-bold">
                            <StoreLogo size="md" />
                            {store.name}
                        </span>
                        <p className="mt-3 max-w-xs text-sm leading-relaxed text-[var(--store-muted)]">{store.tagline || t('store.hero_subtitle')}</p>
                        <span className="mt-4 inline-block rounded-lg border border-[var(--store-border)] px-3 py-1 text-xs font-semibold text-[var(--store-muted)]">
                            {store.currency}
                        </span>
                    </div>
                    <div>
                        <h3 className="mb-4 text-sm font-bold tracking-wider uppercase">{t('store.footer_shop')}</h3>
                        <ul className="space-y-2.5 text-sm text-[var(--store-muted)]">
                            <li>
                                <Link href="/products" className="transition hover:text-[var(--store-accent)]">
                                    {t('store.products')}
                                </Link>
                            </li>
                            <li>
                                <Link href="/search" className="transition hover:text-[var(--store-accent)]">
                                    {t('store.search')}
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 className="mb-4 text-sm font-bold tracking-wider uppercase">{t('store.footer_account')}</h3>
                        <ul className="space-y-2.5 text-sm text-[var(--store-muted)]">
                            <li>
                                <Link href="/cart" className="transition hover:text-[var(--store-accent)]">
                                    {t('store.cart')}
                                </Link>
                            </li>
                            <li>
                                <Link href="/wishlist" className="transition hover:text-[var(--store-accent)]">
                                    {t('store.wishlist')}
                                </Link>
                            </li>
                            <li>
                                <Link href="/account/orders" className="transition hover:text-[var(--store-accent)]">
                                    {t('store.orders')}
                                </Link>
                            </li>
                            <li>
                                <Link href="/account" className="transition hover:text-[var(--store-accent)]">
                                    {t('store.account')}
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 className="mb-4 text-sm font-bold tracking-wider uppercase">{t('store.contact_title')}</h3>
                        {store.address || store.email || store.phone ? (
                            <ul className="space-y-2.5 text-sm text-[var(--store-muted)]">
                                {store.address && (
                                    <li className="flex items-start gap-2">
                                        <MapPin className="mt-0.5 h-4 w-4 shrink-0" />
                                        <span>
                                            {store.address}
                                            {store.city ? `, ${store.city}` : ''}
                                        </span>
                                    </li>
                                )}
                                {store.email && (
                                    <li>
                                        <a href={`mailto:${store.email}`} className="flex items-center gap-2 transition hover:text-[var(--store-accent)]">
                                            <Mail className="h-4 w-4 shrink-0" />
                                            <span className="break-all">{store.email}</span>
                                        </a>
                                    </li>
                                )}
                                {store.phone && (
                                    <li>
                                        <a href={`tel:${store.phone.replace(/\s+/g, '')}`} className="flex items-center gap-2 transition hover:text-[var(--store-accent)]">
                                            <Phone className="h-4 w-4 shrink-0" />
                                            <span dir="ltr">{store.phone}</span>
                                        </a>
                                    </li>
                                )}
                            </ul>
                        ) : (
                            <>
                                <p className="text-sm leading-relaxed text-[var(--store-muted)]">{t('store.perk_support_text')}</p>
                                <Link
                                    href="/products"
                                    className="mt-4 inline-flex rounded-lg bg-[var(--store-accent)] px-5 py-2.5 text-sm font-semibold text-[var(--store-accent-ink)] transition hover:opacity-90"
                                >
                                    {t('store.shop_now')}
                                </Link>
                            </>
                        )}
                    </div>
                </div>
                <div className="border-t border-[var(--store-border)]">
                    <div className="store-container flex flex-wrap items-center justify-between gap-2 py-5 text-xs text-[var(--store-muted)]">
                        <p>
                            &copy; {new Date().getFullYear()} {store.name}. {t('store.footer_rights')}
                        </p>
                        <div className="flex gap-4">
                            <Link href="/products" className="transition hover:text-[var(--store-accent)]">
                                {t('store.products')}
                            </Link>
                            <Link href="/cart" className="transition hover:text-[var(--store-accent)]">
                                {t('store.cart')}
                            </Link>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
}
