import { useStore } from '@/lib/store';
import { ShoppingBag } from 'lucide-react';

const SIZES = {
    sm: 'h-8 w-8 rounded-xl text-xs',
    md: 'h-10 w-10 rounded-lg text-sm',
    lg: 'h-12 w-12 rounded-lg text-base',
} as const;

const ICONS = {
    sm: 'h-4 w-4',
    md: 'h-5 w-5',
    lg: 'h-6 w-6',
} as const;

/**
 * Store brand mark: uploaded logo when set, otherwise a signature
 * gradient badge with a star dot. Used in header, drawer, footer.
 */
export default function StoreLogo({ size = 'md' }: { size?: keyof typeof SIZES }) {
    const store = useStore();

    if (store.logo) {
        return <img src={store.logo} alt={store.name} className={`${SIZES[size]} object-cover shadow-md ring-1 ring-black/10`} />;
    }

    return (
        <span
            className={`relative flex ${SIZES[size]} shrink-0 items-center justify-center bg-gradient-to-br from-[var(--store-accent)] to-[var(--store-accent-strong)] font-black text-[var(--store-accent-ink)] shadow-lg ring-1 ring-black/10`}
        >
            <ShoppingBag className={ICONS[size]} strokeWidth={2.25} />
            <span className="absolute -top-0.5 -end-0.5 h-2.5 w-2.5 rounded-lg bg-[var(--store-star)] ring-2 ring-[var(--store-bg)]" />
        </span>
    );
}
