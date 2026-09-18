import { placeholderImage } from '@/lib/placeholder';
import { useEffect, useState } from 'react';

/**
 * Product photo with graceful fallbacks: original → seeded random photo →
 * styled monogram block (e.g. when offline). Never renders a broken image.
 */
export default function ProductImage({
    src,
    seed,
    alt,
    className = 'h-full w-full object-cover',
    eager = false,
}: {
    src: string | null | undefined;
    seed: string | number;
    alt: string;
    className?: string;
    eager?: boolean;
}) {
    const [stage, setStage] = useState(src ? 0 : 1);

    useEffect(() => {
        setStage(src ? 0 : 1);
    }, [src]);

    if (stage >= 2) {
        return (
            <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-[var(--store-accent-soft)] to-[var(--store-card-hover)]">
                <span className="text-3xl font-black text-[var(--store-accent)]">{alt.charAt(0).toUpperCase()}</span>
            </div>
        );
    }

    return (
        <img
            src={stage === 0 && src ? src : placeholderImage(seed)}
            alt={alt}
            loading={eager ? 'eager' : 'lazy'}
            decoding="async"
            onError={() => setStage((s) => s + 1)}
            className={className}
            draggable={false}
        />
    );
}
