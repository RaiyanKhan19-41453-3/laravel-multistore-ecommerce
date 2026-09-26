import { useState } from 'react';

interface MethodBrand {
    match: RegExp;
    label: string;
    img?: string;
    className: string;
}

const METHODS: MethodBrand[] = [
    { match: /bkash/i, label: 'bKash', img: '/images/payments/bkash.png', className: 'bg-white' },
    { match: /nagad/i, label: 'Nagad', img: '/images/payments/nagad.png', className: 'bg-white' },
    { match: /rocket|dbbl/i, label: 'Rocket', className: 'bg-[#8c3494] text-white' },
    { match: /cod|cash/i, label: 'COD', className: 'bg-[#15803d] text-white' },
    { match: /visa/i, label: 'VISA', img: '/images/payments/visa.svg', className: 'bg-white' },
    { match: /master/i, label: 'Mastercard', img: '/images/payments/mastercard.svg', className: 'bg-white' },
    { match: /amex|american/i, label: 'Amex', img: '/images/payments/americanexpress.svg', className: 'bg-white' },
    { match: /stripe/i, label: 'Card', className: 'bg-[#635bff] text-white' },
    { match: /tabby/i, label: 'Tabby', className: 'bg-[#3e1fdb] text-white' },
    { match: /moyasar/i, label: 'Moyasar', className: 'bg-[#0e7c6b] text-white' },
];

function PayBadge({ brand, label }: { brand: MethodBrand; label: string }) {
    const [failed, setFailed] = useState(false);

    if (!brand.img || failed) {
        return (
            <span
                className={`rounded-md px-2 py-0.5 text-[11px] font-black tracking-wide whitespace-nowrap ${brand.img ? 'bg-[var(--store-card-hover)] text-[var(--store-muted)]' : brand.className}`}
            >
                {brand.img ? label : brand.label}
            </span>
        );
    }

    return (
        <span className={`inline-flex h-6 items-center rounded-md px-1.5 ${brand.className}`}>
            <img src={brand.img} alt={label} loading="lazy" className="h-4 w-auto object-contain" onError={() => setFailed(true)} />
        </span>
    );
}

/**
 * Payment brand marks: real logos where available, colored text chips
 * otherwise. Unknown methods fall back to a neutral chip.
 */
export default function PayBadges({ methods }: { methods: { value: string; label: string }[] }) {
    if (methods.length === 0) return null;

    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            {methods.map((m) => {
                const known = METHODS.find((k) => k.match.test(m.value) || k.match.test(m.label));
                const brand = known ?? { match: /$^/, label: m.label, className: 'bg-[var(--store-card-hover)] text-[var(--store-muted)]' };
                return <PayBadge key={m.value} brand={brand} label={m.label} />;
            })}
        </span>
    );
}
