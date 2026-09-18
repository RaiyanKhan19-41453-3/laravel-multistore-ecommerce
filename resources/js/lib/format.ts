const CURRENCIES: Record<string, { symbol: string; locale: string; decimals: number }> = {
    BDT: { symbol: '৳', locale: 'en-US', decimals: 0 },
    SAR: { symbol: 'ر.س', locale: 'ar-SA', decimals: 2 },
    USD: { symbol: '$', locale: 'en-US', decimals: 2 },
    EUR: { symbol: '€', locale: 'de-DE', decimals: 2 },
    AED: { symbol: 'د.إ', locale: 'ar-AE', decimals: 2 },
    INR: { symbol: '₹', locale: 'en-IN', decimals: 0 },
};

export function getActiveCurrency(): string {
    if (typeof document !== 'undefined') {
        const fromDom = document.documentElement.dataset.currency;

        if (fromDom) return fromDom.toUpperCase();
    }

    if (typeof window !== 'undefined') {
        const fromWindow = (window as unknown as { __STORE__?: { currency?: string } }).__STORE__?.currency;

        if (fromWindow) return fromWindow.toUpperCase();
    }

    return 'BDT';
}

export function formatPrice(value: number | string, currency?: string): string {
    const code = (currency ?? getActiveCurrency()).toUpperCase();
    const meta = CURRENCIES[code] ?? CURRENCIES.BDT;
    const number = Number(value);

    try {
        const formatted = new Intl.NumberFormat(meta.locale, {
            minimumFractionDigits: meta.decimals,
            maximumFractionDigits: Math.max(meta.decimals, 2),
        }).format(number);

        return `${meta.symbol}${formatted}`;
    } catch {
        return `${meta.symbol}${number.toLocaleString('en-US', {
            minimumFractionDigits: meta.decimals,
            maximumFractionDigits: 2,
        })}`;
    }
}
