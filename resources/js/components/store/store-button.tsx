import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Variant = 'primary' | 'soft' | 'outline';

const VARIANTS: Record<Variant, string> = {
    primary:
        'bg-gradient-to-b from-[var(--store-accent)] to-[var(--store-accent-strong)] text-[var(--store-accent-ink)] shadow-lg shadow-[var(--store-accent)]/25 hover:shadow-xl hover:shadow-[var(--store-accent)]/30 hover:brightness-110 active:scale-[0.97]',
    soft: 'bg-[var(--store-accent-soft)] text-[var(--store-accent)] hover:opacity-85 active:scale-[0.97]',
    outline:
        'border border-[var(--store-border)] bg-[var(--store-card)] text-[var(--store-text)] hover:border-[var(--store-accent)] hover:text-[var(--store-accent)] active:scale-[0.97]',
};

interface StoreButtonProps {
    children: ReactNode;
    href?: string;
    onClick?: () => void;
    type?: 'button' | 'submit';
    disabled?: boolean;
    variant?: Variant;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}

/**
 * Signature storefront button: pill shape, gradient primary with a shine
 * sweep on hover, and a press-down active state. Use for every CTA so
 * buttons feel designed instead of default.
 */
export default function StoreButton({
    children,
    href,
    onClick,
    type = 'button',
    disabled = false,
    variant = 'primary',
    size = 'md',
    className = '',
}: StoreButtonProps) {
    const sizes = {
        sm: 'px-4 py-1.5 text-xs',
        md: 'px-6 py-2.5 text-sm',
        lg: 'px-8 py-3.5 text-sm',
    } as const;

    const classes = `group/btn relative inline-flex items-center justify-center gap-2 overflow-hidden rounded-full font-bold transition duration-200 disabled:cursor-not-allowed disabled:opacity-50 disabled:saturate-50 ${VARIANTS[variant]} ${sizes[size]} ${className}`;

    const shine = (
        <span
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/30 to-transparent transition-transform duration-700 ease-out group-hover/btn:translate-x-full motion-reduce:transition-none"
        />
    );

    if (href !== undefined) {
        return (
            <Link href={href} onClick={onClick} className={classes}>
                {shine}
                {children}
            </Link>
        );
    }

    return (
        <button type={type} onClick={onClick} disabled={disabled} className={classes}>
            {shine}
            {children}
        </button>
    );
}
