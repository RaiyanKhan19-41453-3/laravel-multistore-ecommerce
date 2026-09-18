import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface MenuPromo {
    image: string | null;
    title: string | null;
    link: string;
}

export interface MenuNode {
    id: number;
    title: string;
    url: string;
    click_behavior: 'navigate' | 'expand';
    display: 'auto' | 'mega' | 'dropdown';
    has_children: boolean;
    children: MenuNode[];
    promo: MenuPromo | null;
}

function isExternal(url: string): boolean {
    return /^https?:\/\//i.test(url);
}

function isActive(url: string): boolean {
    if (isExternal(url) || typeof window === 'undefined') return false;
    const path = url.split('?')[0].replace(/\/+$/, '') || '/';
    const current = window.location.pathname.replace(/\/+$/, '') || '/';
    return path !== '/' && (current === path || current.startsWith(`${path}/`));
}

export function SmartLink({ href, className, children, onClick }: { href: string; className?: string; children: React.ReactNode; onClick?: () => void }) {
    if (isExternal(href)) {
        return (
            <a href={href} className={className} onClick={onClick}>
                {children}
            </a>
        );
    }
    return (
        <Link href={href} className={className} onClick={onClick}>
            {children}
        </Link>
    );
}

function chunk<T>(items: T[], size: number): T[][] {
    const out: T[][] = [];
    for (let i = 0; i < items.length; i += size) out.push(items.slice(i, i + size));
    return out;
}

export function DesktopNav({ items }: { items: MenuNode[] }) {
    const [openId, setOpenId] = useState<number | null>(null);

    useEffect(() => {
        if (openId === null) return;
        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpenId(null);
        };
        window.addEventListener('keydown', handleKey);
        return () => window.removeEventListener('keydown', handleKey);
    }, [openId]);

    return (
        <nav aria-label="Primary" className="ms-4 hidden items-center gap-1 text-sm font-medium lg:flex">
            {items.map((item) => {
                const expandable = item.has_children;
                // Explicit choice wins; auto goes full-width only when
                // there are enough links to fill columns.
                const mega = expandable && (item.display === 'mega' || (item.display === 'auto' && item.children.length > 3));
                const open = openId === item.id;

                return (
                    <div key={item.id} className={mega ? '' : 'relative'} onMouseEnter={() => expandable && setOpenId(item.id)} onMouseLeave={() => setOpenId(null)}>
                        {expandable && item.click_behavior === 'expand' ? (
                            <button
                                type="button"
                                aria-expanded={open}
                                aria-haspopup="true"
                                onClick={() => setOpenId(open ? null : item.id)}
                                className={`flex items-center gap-1 rounded-lg px-3.5 py-2 transition hover:bg-[var(--store-card-hover)] hover:text-[var(--store-text)] ${
                                    open || isActive(item.url) ? 'text-[var(--store-text)]' : 'text-[var(--store-muted)]'
                                }`}
                            >
                                {item.title}
                                <ChevronDown className={`h-3.5 w-3.5 transition-transform ${open ? 'rotate-180' : ''}`} />
                            </button>
                        ) : (
                            <SmartLink
                                href={item.url}
                                className={`flex items-center gap-1 rounded-lg px-3.5 py-2 transition hover:bg-[var(--store-card-hover)] hover:text-[var(--store-text)] ${
                                    open || isActive(item.url) ? 'text-[var(--store-text)]' : 'text-[var(--store-muted)]'
                                }`}
                            >
                                {item.title}
                                {expandable && <ChevronDown className={`h-3.5 w-3.5 transition-transform ${open ? 'rotate-180' : ''}`} />}
                            </SmartLink>
                        )}

                        {expandable && open && !mega && (
                            <div className="absolute top-full start-0 z-50 min-w-56 pt-2">
                                <div className="overflow-hidden rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-1.5 shadow-[var(--store-shadow)]">
                                    {item.children.map((child) => (
                                        <SmartLink
                                            key={child.id}
                                            href={child.url}
                                            onClick={() => setOpenId(null)}
                                            className="block rounded-xl px-3.5 py-2 text-sm transition hover:bg-[var(--store-card-hover)] hover:text-[var(--store-accent)]"
                                        >
                                            {child.title}
                                        </SmartLink>
                                    ))}
                                </div>
                            </div>
                        )}

                        {expandable && open && mega && (
                            <div className="absolute inset-x-4 top-full z-50 pt-2">
                                <div className="overflow-hidden rounded-xl border border-[var(--store-border)] bg-[var(--store-card)] shadow-[var(--store-shadow)]">
                                    <div className={`grid gap-8 p-6 md:p-8 ${item.promo ? 'md:grid-cols-[1fr_240px]' : ''}`}>
                                        <div
                                            className="grid gap-8"
                                            style={{ gridTemplateColumns: `repeat(${Math.min(4, Math.max(1, Math.ceil(item.children.length / 6)))}, minmax(0, 1fr))` }}
                                        >
                                            {chunk(item.children, 6).map((column, i) => (
                                                <ul key={i} className="space-y-1">
                                                    {column.map((child) => (
                                                        <li key={child.id}>
                                                            <SmartLink
                                                                href={child.url}
                                                                onClick={() => setOpenId(null)}
                                                                className="block rounded-lg px-2 py-1.5 text-sm text-[var(--store-muted)] transition hover:bg-[var(--store-card-hover)] hover:text-[var(--store-accent)]"
                                                            >
                                                                {child.title}
                                                            </SmartLink>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ))}
                                        </div>
                                        {item.promo && (
                                            <SmartLink
                                                href={item.promo.link}
                                                onClick={() => setOpenId(null)}
                                                className="group/promo relative hidden overflow-hidden rounded-lg md:block"
                                            >
                                                {item.promo.image ? (
                                                    <img src={item.promo.image} alt={item.promo.title ?? ''} loading="lazy" className="h-full min-h-48 w-full object-cover" />
                                                ) : (
                                                    <div className="h-full min-h-48 bg-[var(--store-accent-soft)]" />
                                                )}
                                                {item.promo.title && (
                                                    <span className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-4 pt-10 text-sm font-bold text-white">
                                                        {item.promo.title}
                                                    </span>
                                                )}
                                            </SmartLink>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                );
            })}
        </nav>
    );
}

export function MobileNav({ items, onNavigate }: { items: MenuNode[]; onNavigate: () => void }) {
    const [expanded, setExpanded] = useState<number | null>(null);

    return (
        <nav aria-label="Primary" className="flex flex-col gap-1 text-[15px] font-medium">
            {items.map((item) => {
                const expandable = item.has_children;
                const isOpen = expanded === item.id;

                return (
                    <div key={item.id}>
                        <div className="flex items-center gap-1">
                            {expandable && item.click_behavior === 'expand' ? (
                                <span className="flex-1 rounded-xl px-3 py-2.5">{item.title}</span>
                            ) : (
                                <SmartLink
                                    href={item.url}
                                    onClick={onNavigate}
                                    className="flex-1 rounded-xl px-3 py-2.5 transition hover:bg-[var(--store-card-hover)]"
                                >
                                    {item.title}
                                </SmartLink>
                            )}
                            {expandable && (
                                <button
                                    type="button"
                                    aria-expanded={isOpen}
                                    aria-label={`${isOpen ? 'Collapse' : 'Expand'} ${item.title}`}
                                    onClick={() => setExpanded(isOpen ? null : item.id)}
                                    className="flex h-9 w-9 items-center justify-center rounded-lg transition hover:bg-[var(--store-card-hover)]"
                                >
                                    <ChevronDown className={`h-4 w-4 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
                                </button>
                            )}
                        </div>
                        {expandable && isOpen && (
                            <div className="ms-3 space-y-0.5 border-s-2 border-[var(--store-border)] ps-2">
                                {item.children.map((child) => (
                                    <SmartLink
                                        key={child.id}
                                        href={child.url}
                                        onClick={onNavigate}
                                        className="block rounded-lg px-3 py-2 text-sm text-[var(--store-muted)] transition hover:bg-[var(--store-card-hover)] hover:text-[var(--store-text)]"
                                    >
                                        {child.title}
                                    </SmartLink>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </nav>
    );
}
