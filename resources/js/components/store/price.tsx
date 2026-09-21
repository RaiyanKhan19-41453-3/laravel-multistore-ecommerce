import { formatPrice } from '@/lib/format';

export default function Price({
    value,
    compareAt,
    size = 'md',
    accent = false,
}: {
    value: number;
    compareAt?: number | null;
    size?: 'sm' | 'md' | 'lg';
    accent?: boolean;
}) {
    const valueClass = size === 'lg' ? 'text-3xl font-bold' : size === 'sm' ? 'text-sm font-bold' : 'text-base font-bold';

    return (
        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <span className={`${valueClass} ${accent ? 'text-[var(--store-accent)]' : 'text-[var(--store-text)]'}`}>{formatPrice(value)}</span>
            {!!compareAt && compareAt > value && (
                <span className="text-xs text-[var(--store-muted)] line-through">{formatPrice(compareAt)}</span>
            )}
        </div>
    );
}
