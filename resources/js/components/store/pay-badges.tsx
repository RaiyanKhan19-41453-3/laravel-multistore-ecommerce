const METHODS: { match: RegExp; label: string; className: string }[] = [
    { match: /bkash/i, label: 'bKash', className: 'bg-[#e2136e] text-white' },
    { match: /nagad/i, label: 'Nagad', className: 'bg-[#f6921e] text-white' },
    { match: /rocket|dbbl/i, label: 'Rocket', className: 'bg-[#8c3494] text-white' },
    { match: /cod|cash/i, label: 'COD', className: 'bg-[#15803d] text-white' },
    { match: /visa/i, label: 'VISA', className: 'bg-[#1a1f71] text-white' },
    { match: /master/i, label: 'Mastercard', className: 'bg-[#3b3b3b] text-white' },
    { match: /stripe/i, label: 'Card', className: 'bg-[#635bff] text-white' },
    { match: /tabby/i, label: 'Tabby', className: 'bg-[#3e1fdb] text-white' },
    { match: /moyasar/i, label: 'Moyasar', className: 'bg-[#0e7c6b] text-white' },
];

/**
 * BD payment brand chips (bKash, Nagad, Rocket, COD…). Unknown methods
 * fall back to a neutral chip with the raw label.
 */
export default function PayBadges({ methods }: { methods: { value: string; label: string }[] }) {
    if (methods.length === 0) return null;

    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            {methods.map((m) => {
                const known = METHODS.find((k) => k.match.test(m.value) || k.match.test(m.label));
                return (
                    <span
                        key={m.value}
                        className={`rounded-md px-2 py-0.5 text-[11px] font-black tracking-wide whitespace-nowrap ${known ? known.className : 'bg-[var(--store-card-hover)] text-[var(--store-muted)]'}`}
                    >
                        {known ? known.label : m.label}
                    </span>
                );
            })}
        </span>
    );
}
