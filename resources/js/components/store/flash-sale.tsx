import type { ProductSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { Zap } from 'lucide-react';
import { useEffect, useState } from 'react';
import ProductCard from './product-card';

function msToMidnight(): number {
    const now = new Date();
    const end = new Date(now);
    end.setHours(23, 59, 59, 999);
    return Math.max(0, end.getTime() - now.getTime());
}

function pad(n: number): string {
    return String(n).padStart(2, '0');
}

function TimeBox({ value, label }: { value: string; label: string }) {
    return (
        <span className="flex flex-col items-center">
            <span className="min-w-9 rounded-md bg-white/95 px-1.5 py-1 text-center text-sm font-black tabular-nums text-[#b91c1c] shadow">
                {value}
            </span>
            <span className="mt-1 text-[10px] font-bold tracking-wider text-white/80 uppercase">{label}</span>
        </span>
    );
}

/**
 * Daraz-style flash sale rail: gradient header with a live countdown to
 * midnight plus a horizontal rail of discounted products.
 */
export default function FlashSale({
    items,
    title,
    endsLabel,
    shopAllLabel,
    hoursLabel,
    minutesLabel,
    secondsLabel,
}: {
    items: ProductSummary[];
    title: string;
    endsLabel: string;
    shopAllLabel: string;
    hoursLabel: string;
    minutesLabel: string;
    secondsLabel: string;
}) {
    const [remaining, setRemaining] = useState(msToMidnight);

    useEffect(() => {
        if (typeof window === 'undefined') return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const id = setInterval(() => setRemaining(msToMidnight()), 1000);
        return () => clearInterval(id);
    }, []);

    if (items.length === 0) return null;

    const totalSeconds = Math.floor(remaining / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return (
        <section aria-label={endsLabel} className="store-container pt-10">
            <div className="overflow-hidden rounded-xl shadow-[var(--store-shadow)]">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-3 bg-gradient-to-r from-[#e11d2e] to-[#ff6a3d] px-4 py-3 sm:px-6">
                    <span className="flex items-center gap-2 text-base font-black tracking-tight text-white sm:text-lg">
                        <Zap className="h-5 w-5 fill-yellow-300 text-yellow-300" />
                        {title}
                    </span>
                    <span className="flex items-center gap-1.5 text-xs font-bold text-white">
                        {endsLabel}
                        <span className="flex items-start gap-1" role="timer" aria-live="off">
                            <TimeBox value={pad(hours)} label={hoursLabel} />
                            <span className="pt-1 font-black">:</span>
                            <TimeBox value={pad(minutes)} label={minutesLabel} />
                            <span className="pt-1 font-black">:</span>
                            <TimeBox value={pad(seconds)} label={secondsLabel} />
                        </span>
                    </span>
                    <Link
                        href="/products?on_sale=1"
                        className="ms-auto shrink-0 rounded-lg border border-white/60 px-4 py-1.5 text-xs font-bold text-white transition hover:bg-white/15"
                    >
                        {shopAllLabel}
                    </Link>
                </div>
                <div className="border border-t-0 border-[var(--store-border)] bg-[var(--store-card)] px-3 py-4 sm:px-4">
                    <div className="flex snap-x gap-3 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        {items.slice(0, 12).map((product) => (
                            <div key={product.id} className="w-[150px] shrink-0 snap-start sm:w-[180px]">
                                <ProductCard product={product} compact />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
