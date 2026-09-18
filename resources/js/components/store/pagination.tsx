import { useT } from '@/lib/store';
import type { PaginatedData } from '@/types';
import { router } from '@inertiajs/react';

export default function Pagination<T>({ data, onPageChange }: { data: PaginatedData<T>; onPageChange?: (url: string) => void }) {
    const t = useT();

    if (data.last_page <= 1) return null;

    const goTo = (url: string | null) => {
        if (!url) return;
        if (onPageChange) {
            onPageChange(url);
        } else {
            router.get(url, {}, { preserveState: true, replace: true });
        }
    };

    return (
        <nav className="flex items-center justify-between border-t border-[var(--store-border)] px-4 py-3 sm:px-0">
            <div className="flex w-0 flex-1 gap-2">
                {data.prev_page_url && (
                    <button
                        type="button"
                        onClick={() => goTo(data.prev_page_url)}
                        className="inline-flex items-center rounded-md border border-[var(--store-border)] px-3 py-2 text-sm text-[var(--store-text)] hover:bg-[var(--store-card)]"
                    >
                        ← {t('store.prev')}
                    </button>
                )}
            </div>
            <span className="text-sm text-[var(--store-muted)]">
                {data.current_page} / {data.last_page}
            </span>
            <div className="flex w-0 flex-1 justify-end gap-2">
                {data.next_page_url && (
                    <button
                        type="button"
                        onClick={() => goTo(data.next_page_url)}
                        className="inline-flex items-center rounded-md border border-[var(--store-border)] px-3 py-2 text-sm text-[var(--store-text)] hover:bg-[var(--store-card)]"
                    >
                        {t('store.next')} →
                    </button>
                )}
            </div>
        </nav>
    );
}
