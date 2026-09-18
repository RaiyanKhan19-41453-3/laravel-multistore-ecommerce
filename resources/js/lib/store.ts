import ar from '@/i18n/ar.json';
import en from '@/i18n/en.json';
import { usePage } from '@inertiajs/react';

export interface StoreShared {
    name: string;
    tagline: string;
    logo: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    country: string;
    currency: string;
    currencySymbol: string;
    locale: string;
}

const dictionaries: Record<string, Record<string, string>> = { en, ar };

export function useStore(): StoreShared {
    const { store } = usePage<{ store?: StoreShared }>().props;

    return (
        store ?? {
            name: 'Store',
            tagline: '',
            logo: '',
            email: '',
            phone: '',
            address: '',
            city: '',
            country: 'BD',
            currency: 'BDT',
            currencySymbol: '৳',
            locale: 'en',
        }
    );
}

export function useDirection(): 'rtl' | 'ltr' {
    const { direction } = usePage<{ direction?: 'rtl' | 'ltr' }>().props;

    return direction ?? 'ltr';
}

export function useLocale(): string {
    const { locale } = usePage<{ locale?: string }>().props;

    return locale ?? 'en';
}

export function translate(locale: string, key: string): string {
    const dict = dictionaries[locale] ?? dictionaries.en;

    return dict[key] ?? dictionaries.en[key] ?? key;
}

export function useT(): (key: string) => string {
    const locale = useLocale();

    return (key: string) => translate(locale, key);
}
