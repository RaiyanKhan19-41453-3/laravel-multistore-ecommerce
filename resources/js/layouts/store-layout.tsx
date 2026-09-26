import { apiStore, clearAuth, getUser, type StoreUser } from '@/lib/auth';
import { useDirection, useLocale, useStore, useT } from '@/lib/store';
import type { CartSummary } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { BadgePercent, Heart, Home, LayoutGrid, LogOut, Mail, MapPin, Menu, Package, Phone, ShoppingBag, Tag, User as UserIcon, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import MiniCart, { openMiniCart } from '@/components/store/mini-cart';
import PayBadges from '@/components/store/pay-badges';
import QuickViewModal from '@/components/store/quick-view';
import SearchBox from '@/components/store/search-box';
import StoreButton from '@/components/store/store-button';
import StoreLogo from '@/components/store/store-logo';
import { CategoryMenu, type NavCategory } from '@/components/store/category-nav';
import { DesktopNav, MobileNav, type MenuNode } from '@/components/store/site-nav';

const NAV_LINKS = [
    { href: '/', key: 'store.home' },
    { href: '/products', key: 'store.products' },
] as const;

const PAY_BADGE_METHODS = [
    { value: 'bkash', label: 'bKash' },
    { value: 'nagad', label: 'Nagad' },
    { value: 'rocket', label: 'Rocket' },
    { value: 'cod', label: 'COD' },
    { value: 'visa', label: 'VISA' },
    { value: 'mastercard', label: 'Mastercard' },
];

export default function StoreLayout({ title, children }: { title?: string; children: ReactNode }) {
    const [user, setUser] = useState<StoreUser | null>(null);
    const [cartCount, setCartCount] = useState(0);
    const [menuOpen, setMenuOpen] = useState(false);
    const { nav } = usePage<{ nav?: { menus: MenuNode[]; categories: NavCategory[] } }>().props;
    const menuItems = nav?.menus ?? [];
    const categories = nav?.categories ?? [];
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
            <div className="bg-[var(--store-accent-strong)] px-4 py-2 text-center text-xs font-semibold tracking-wide text-[var(--store-accent-ink)]">
                <span className="inline-flex items-center gap-1.5">
                    <Tag className="h-3.5 w-3.5" />
                    {t('store.promo_text')}
                </span>
            </div>

            <header className="sticky top-0 z-40 bg-[var(--store-accent)] text-[var(--store-accent-ink)] shadow-md">
                <div className="store-container relative grid h-16 grid-cols-[1fr_auto_1fr] items-center gap-3">
                    {/* Left: search */}
                    <div className="flex min-w-0 items-center gap-2">
                        <button
                            type="button"
                            aria-label="Open menu"
                            onClick={() => setMenuOpen(true)}
                            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition hover:bg-white/15 lg:hidden"
                        >
                            <Menu className="h-5 w-5" />
                        </button>

                    {/* Big search */}
                    <div className="hidden w-full max-w-md min-w-0 md:block">
                        <SearchBox />
                    </div>
                    </div>

                    {/* Center: logo */}
                    <Link href="/" className="flex items-center justify-self-center gap-2.5 text-[var(--store-accent-ink)]" aria-label={store.name}>
                        <StoreLogo size="md" />
                        {store.show_store_name && (
                            <span className="store-display hidden text-[22px] leading-none font-bold tracking-tight sm:inline">{store.name}</span>
                        )}
                    </Link>

                    <div className="flex items-center justify-end gap-1">
                        {user && (
                            <Link
                                href="/wishlist"
                                title={t('store.wishlist')}
                                className="flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/15"
                            >
                                <Heart className="h-5 w-5" />
                            </Link>
                        )}
                        <button
                            type="button"
                            onClick={openMiniCart}
                            title={t('store.cart')}
                            className="relative flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/15"
                        >
                            <ShoppingBag className="h-5 w-5" />
                            {cartCount > 0 && (
                                <span className="absolute top-0.5 end-0.5 flex h-5 min-w-5 items-center justify-center rounded-lg bg-white px-1 text-[11px] font-black text-[var(--store-accent)]">
                                    {cartCount}
                                </span>
                            )}
                        </button>
                        {user ? (
                            <div className="flex items-center">
                                <Link
                                    href="/account"
                                    title={user.name}
                                    className="flex h-10 items-center gap-2 rounded-lg px-2 transition hover:bg-white/15"
                                >
                                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 text-sm font-bold text-[var(--store-accent-ink)]">
                                        {user.name.charAt(0).toUpperCase()}
                                    </span>
                                    <span className="hidden max-w-24 truncate text-sm font-medium xl:inline">{user.name}</span>
                                </Link>
                                <button
                                    type="button"
                                    onClick={logout}
                                    title={t('store.logout')}
                                    className="hidden h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/15 lg:flex"
                                >
                                    <LogOut className="h-4.5 w-4.5" />
                                </button>
                            </div>
                        ) : (
                            <StoreButton href="/account/login" size="sm" className="ms-1 hidden sm:inline-flex">
                                {t('store.login')}
                            </StoreButton>
                        )}
                    </div>
                </div>

                {/* Mobile search */}
                <div className="px-4 pt-1 pb-2.5 md:hidden">
                    <SearchBox compact />
                </div>
            </header>

            {/* Category tier */}
            <div className="hidden border-b border-[var(--store-border)] bg-[var(--store-card)] lg:block">
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
                        <BadgePercent className="h-3.5 w-3.5" />
                        {t('store.deals')}
                    </Link>
                </div>
            </div>

            {/* Mobile drawer */}
            {menuOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setMenuOpen(false)} />
                    <div className="absolute top-0 bottom-0 start-0 flex w-72 flex-col bg-[var(--store-bg)] p-5 shadow-xl">
                        <div className="mb-6 flex items-center justify-between">
                            <span className="flex items-center gap-2 font-bold">
                                <StoreLogo size="sm" />
                                {store.show_store_name && store.name}
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
                            <Link
                                href="/order-confirmation"
                                onClick={() => setMenuOpen(false)}
                                className="flex items-center gap-2 rounded-xl px-3 py-2.5 transition hover:bg-[var(--store-card-hover)]"
                            >
                                <Package className="h-4 w-4" /> {t('store.track_order')}
                            </Link>
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
                                    <StoreButton href="/account/login" className="w-full" onClick={() => setMenuOpen(false)}>
                                        {t('store.login')}
                                    </StoreButton>
                                    <StoreButton
                                        href="/account/register"
                                        variant="outline"
                                        className="w-full"
                                        onClick={() => setMenuOpen(false)}
                                    >
                                        {t('store.register')}
                                    </StoreButton>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            <main className="flex-1 pb-20 md:pb-0">{children}</main>
            <MiniCart />
            <QuickViewModal />

            {/* Mobile bottom tab bar (BD shoppers live on phones) */}
            <MobileTabBar
                cartCount={cartCount}
                userName={user?.name ?? null}
                onOpenMenu={() => setMenuOpen(true)}
                homeLabel={t('store.home')}
                categoriesLabel={t('store.categories')}
                cartLabel={t('store.cart')}
                accountLabel={t('store.account')}
            />

            <footer className="mt-16 border-t border-[var(--store-border)] bg-[var(--store-card)]">
                <div className="store-container grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <span className="flex items-center gap-2 text-base font-bold">
                            <StoreLogo size="md" />
                            {store.show_store_name && store.name}
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
                                <StoreButton href="/products" size="sm" className="mt-4">
                                    {t('store.shop_now')}
                                </StoreButton>
                            </>
                        )}
                    </div>
                </div>
                <div className="border-t border-[var(--store-border)]">
                    <div className="store-container flex flex-wrap items-center justify-between gap-2 py-5 text-xs text-[var(--store-muted)]">
                        <p>
                            &copy; {new Date().getFullYear()} {store.name}. {t('store.footer_rights')}
                        </p>
                        <PayBadges methods={PAY_BADGE_METHODS} />
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

function MobileTabBar({
    cartCount,
    userName,
    onOpenMenu,
    homeLabel,
    categoriesLabel,
    cartLabel,
    accountLabel,
}: {
    cartCount: number;
    userName: string | null;
    onOpenMenu: () => void;
    homeLabel: string;
    categoriesLabel: string;
    cartLabel: string;
    accountLabel: string;
}) {
    const { url } = usePage();
    const path = url.split('?')[0];

    const itemClass = (active: boolean) =>
        `flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-bold transition ${
            active ? 'text-[var(--store-accent)]' : 'text-[var(--store-muted)]'
        }`;

    return (
        <nav aria-label="Mobile" className="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--store-border)] bg-[var(--store-card)] pb-[env(safe-area-inset-bottom)] md:hidden">
            <div className="flex items-stretch">
                <Link href="/" className={itemClass(path === '/')}>
                    <Home className="h-5 w-5" />
                    {homeLabel}
                </Link>
                <button type="button" onClick={onOpenMenu} className={itemClass(false)}>
                    <LayoutGrid className="h-5 w-5" />
                    {categoriesLabel}
                </button>
                <button type="button" onClick={openMiniCart} aria-label={cartLabel} className={`${itemClass(false)} relative`}>
                    <span className="relative">
                        <ShoppingBag className="h-5 w-5" />
                        {cartCount > 0 && (
                            <span className="absolute -top-2 -end-3 flex h-4 min-w-4 items-center justify-center rounded-lg bg-[var(--store-accent)] px-1 text-[9px] font-black text-white">
                                {cartCount}
                            </span>
                        )}
                    </span>
                    {cartLabel}
                </button>
                <Link href={userName ? '/account' : '/account/login'} className={itemClass(path.startsWith('/account') || path.startsWith('/wishlist'))}>
                    <UserIcon className="h-5 w-5" />
                    {userName ?? accountLabel}
                </Link>
            </div>
        </nav>
    );
}
