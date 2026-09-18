import { Minus, Plus } from 'lucide-react';

export default function QuantityStepper({
    value,
    min = 1,
    max,
    onChange,
    small = false,
}: {
    value: number;
    min?: number;
    max?: number;
    onChange: (value: number) => void;
    small?: boolean;
}) {
    const clamp = (next: number) => {
        const lower = Math.max(min, next);
        onChange(max !== undefined ? Math.min(max, lower) : lower);
    };

    const buttonClass = small ? 'h-8 w-8' : 'h-10 w-10';

    return (
        <div
            className={`inline-flex items-center rounded-lg border border-[var(--store-border)] bg-[var(--store-input)] ${
                small ? 'p-0.5' : 'p-1'
            }`}
        >
            <button
                type="button"
                aria-label="Decrease quantity"
                onClick={() => clamp(value - 1)}
                disabled={value <= min}
                className={`${buttonClass} flex items-center justify-center rounded-lg text-[var(--store-muted)] transition hover:bg-[var(--store-card-hover)] hover:text-[var(--store-text)] disabled:opacity-40`}
            >
                <Minus className="h-4 w-4" />
            </button>
            <span className={`min-w-8 text-center font-semibold tabular-nums ${small ? 'text-sm' : 'text-base'}`}>{value}</span>
            <button
                type="button"
                aria-label="Increase quantity"
                onClick={() => clamp(value + 1)}
                disabled={max !== undefined && value >= max}
                className={`${buttonClass} flex items-center justify-center rounded-lg text-[var(--store-muted)] transition hover:bg-[var(--store-card-hover)] hover:text-[var(--store-text)] disabled:opacity-40`}
            >
                <Plus className="h-4 w-4" />
            </button>
        </div>
    );
}
