import { Link } from '@inertiajs/react';

export default function SectionHeading({
    eyebrow,
    title,
    subtitle,
    actionHref,
    actionLabel,
}: {
    eyebrow?: string;
    title: string;
    subtitle?: string;
    actionHref?: string;
    actionLabel?: string;
}) {
    return (
        <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div className="max-w-xl">
                {eyebrow && (
                    <p className="mb-2 text-xs font-bold tracking-[0.18em] text-[var(--store-accent)] uppercase">{eyebrow}</p>
                )}
                <h2 className="store-display text-2xl font-bold tracking-tight text-[var(--store-text)] md:text-3xl">{title}</h2>
                {subtitle && <p className="mt-2 text-sm text-[var(--store-muted)] md:text-base">{subtitle}</p>}
            </div>
            {actionHref && actionLabel && (
                <Link
                    href={actionHref}
                    className="rounded-lg border border-[var(--store-border)] px-5 py-2 text-sm font-semibold text-[var(--store-text)] transition hover:border-[var(--store-accent)] hover:text-[var(--store-accent)]"
                >
                    {actionLabel}
                </Link>
            )}
        </div>
    );
}
