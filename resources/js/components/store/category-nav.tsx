import { useT } from '@/lib/store';
import { Link } from '@inertiajs/react';
import { ChevronDown, LayoutGrid } from 'lucide-react';
import { useState } from 'react';
import StoreButton from './store-button';

export interface NavCategory {
    id: number;
    name: string;
    slug: string;
}

/**
 * BD-shop style category browse button with hover dropdown.
 * Used in the header category tier on desktop.
 */
export function CategoryMenu({ categories }: { categories: NavCategory[] }) {
    const t = useT();
    const [open, setOpen] = useState(false);

    if (categories.length === 0) return null;

    return (
        <div className="relative shrink-0" onMouseEnter={() => setOpen(true)} onMouseLeave={() => setOpen(false)}>
            <StoreButton
                onClick={() => setOpen(!open)}
                size="sm"
                className="shrink-0"
            >
                <LayoutGrid className="h-4 w-4" />
                {t('store.shop_by_category')}
                <ChevronDown className={`h-3.5 w-3.5 transition-transform ${open ? 'rotate-180' : ''}`} />
            </StoreButton>
            {open && (
                <div className="store-menu-panel absolute top-full start-0 z-50 min-w-60 pt-2">
                    <div className="max-h-96 overflow-y-auto rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-1.5 shadow-[var(--store-shadow)]">
                        {categories.map((category, i) => (
                            <Link
                                key={category.id}
                                href={`/categories/${category.slug}`}
                                onClick={() => setOpen(false)}
                                className="store-menu-item block rounded-xl px-3.5 py-2 text-sm transition hover:bg-[var(--store-card-hover)] hover:text-[var(--store-accent)]"
                                style={{ animationDelay: `${Math.min(i, 8) * 18}ms` }}
                            >
                                {category.name}
                            </Link>
                        ))}
                        <Link
                            href="/products"
                            onClick={() => setOpen(false)}
                            className="mt-1 block rounded-xl bg-[var(--store-card-hover)] px-3.5 py-2 text-center text-sm font-bold transition hover:text-[var(--store-accent)]"
                        >
                            {t('store.view_all')}
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
