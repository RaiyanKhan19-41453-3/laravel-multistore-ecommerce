import type { PaginatedData, ProductSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { formatPrice } from '@/lib/format';
import { useT } from '@/lib/store';
import ProductImage from './product-image';

function Highlight({ text, query }: { text: string; query: string }) {
    const q = query.trim();
    if (!q) return <>{text}</>;
    const i = text.toLowerCase().indexOf(q.toLowerCase());
    if (i === -1) return <>{text}</>;
    return (
        <>
            {text.slice(0, i)}
            <mark className="bg-transparent font-bold text-[var(--store-accent)]">{text.slice(i, i + q.length)}</mark>
            {text.slice(i + q.length)}
        </>
    );
}

/**
 * Header search with live product suggestions: thumbnails, prices,
 * keyboard navigation, and match highlighting. Debounced with
 * in-flight request cancellation.
 */
export default function SearchBox({ compact = false }: { compact?: boolean }) {
    const t = useT();
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [items, setItems] = useState<ProductSummary[]>([]);
    const [total, setTotal] = useState(0);
    const [activeIdx, setActiveIdx] = useState(-1);
    const rootRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const q = query.trim();
        if (q.length < 2) {
            setItems([]);
            setTotal(0);
            setOpen(false);
            setActiveIdx(-1);
            return;
        }

        setLoading(true);
        const controller = new AbortController();
        const timer = setTimeout(() => {
            void fetch(`/api/products?search=${encodeURIComponent(q)}&per_page=6`, { signal: controller.signal })
                .then((r) => (r.ok ? r.json() : null))
                .then((json: { success: boolean; data: PaginatedData<ProductSummary> } | null) => {
                    if (json?.success) {
                        setItems(json.data.data);
                        setTotal(json.data.total);
                        setActiveIdx(-1);
                        setOpen(true);
                    }
                })
                .catch((e: unknown) => {
                    if (e instanceof DOMException && e.name === 'AbortError') return;
                })
                .finally(() => setLoading(false));
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    useEffect(() => {
        const onPointerDown = (e: PointerEvent) => {
            if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('pointerdown', onPointerDown);
        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, []);

    const goSearch = (q: string) => {
        const trimmed = q.trim();
        if (!trimmed) return;
        setOpen(false);
        window.location.href = `/search?q=${encodeURIComponent(trimmed)}`;
    };

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Escape') {
            setOpen(false);
            (e.target as HTMLInputElement).blur();
        } else if (e.key === 'ArrowDown' && open && items.length > 0) {
            e.preventDefault();
            setActiveIdx((i) => (i + 1) % items.length);
        } else if (e.key === 'ArrowUp' && open && items.length > 0) {
            e.preventDefault();
            setActiveIdx((i) => (i - 1 + items.length) % items.length);
        } else if (e.key === 'Enter' && open && activeIdx >= 0 && items[activeIdx]) {
            e.preventDefault();
            window.location.href = `/products/${items[activeIdx].slug}`;
        }
    };

    return (
        <div ref={rootRef} className="relative w-full">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    goSearch(query);
                }}
                role="search"
            >
                <Search className="pointer-events-none absolute top-1/2 start-4 h-4 w-4 -translate-y-1/2 text-[var(--store-muted)]" />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setLoading(e.target.value.trim().length >= 2);
                    }}
                    onFocus={() => {
                        if (query.trim().length >= 2 && items.length > 0) setOpen(true);
                    }}
                    onKeyDown={onKeyDown}
                    placeholder={t('store.search')}
                    aria-expanded={open}
                    aria-label={t('store.search')}
                    className={`w-full rounded-lg border-2 border-[var(--store-accent)] bg-[var(--store-input)] pe-12 ps-11 text-sm text-[var(--store-text)] transition outline-none placeholder:text-[var(--store-muted)] ${
                        compact ? 'py-2' : 'py-2.5'
                    }`}
                />
                <button
                    type="submit"
                    aria-label="Search"
                    className="absolute top-1 bottom-1 end-1 flex w-11 items-center justify-center rounded-full bg-gradient-to-b from-[var(--store-accent)] to-[var(--store-accent-strong)] text-[var(--store-accent-ink)] shadow transition hover:brightness-110 active:scale-95"
                >
                    {loading ? <span className="h-4 w-4 animate-spin rounded-lg border-2 border-white/40 border-t-white" /> : <Search className="h-4 w-4" />}
                </button>
            </form>

            {open && query.trim().length >= 2 && (
                <div className="absolute inset-x-0 top-full z-50 pt-2">
                    <div className="overflow-hidden rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] shadow-[var(--store-shadow)]">
                        {items.length === 0 && !loading ? (
                            <button
                                type="button"
                                onClick={() => goSearch(query)}
                                className="block w-full px-4 py-3.5 text-start text-sm text-[var(--store-muted)] transition hover:bg-[var(--store-card-hover)]"
                            >
                                {t('store.no_quick_matches')} “{query.trim()}”.
                            </button>
                        ) : (
                            <>
                                <ul className="max-h-80 overflow-y-auto p-1.5">
                                    {items.map((item, i) => (
                                        <li key={item.id}>
                                            <Link
                                                href={`/products/${item.slug}`}
                                                className={`flex items-center gap-3 rounded-xl px-2 py-2 transition ${
                                                    i === activeIdx ? 'bg-[var(--store-card-hover)]' : 'hover:bg-[var(--store-card-hover)]'
                                                }`}
                                            >
                                                <span className="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-[var(--store-card-hover)]">
                                                    <ProductImage src={item.primary_image} seed={item.id} alt="" />
                                                </span>
                                                <span className="min-w-0 flex-1 truncate text-sm">
                                                    <Highlight text={item.name} query={query} />
                                                </span>
                                                <span className="shrink-0 text-sm font-bold">{formatPrice(item.price)}</span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                                <button
                                    type="button"
                                    onClick={() => goSearch(query)}
                                    className="block w-full border-t border-[var(--store-border)] px-4 py-2.5 text-center text-sm font-bold text-[var(--store-accent)] transition hover:bg-[var(--store-card-hover)]"
                                >
                                    See all {total} result{total === 1 ? '' : 's'}
                                </button>
                            </>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
