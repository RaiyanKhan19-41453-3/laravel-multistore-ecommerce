import { useStore } from '@/lib/store';

const SIZES = {
    sm: 'h-8 w-8 rounded-xl',
    md: 'h-10 w-10 rounded-lg',
    lg: 'h-12 w-12 rounded-lg',
} as const;

/**
 * Store brand mark: uploaded logo when set, otherwise an original
 * shopping-bag mark in Bangladesh green with a red sun dot, a nod to
 * the national flag. White tile so it pops on the green header and
 * stays crisp on light surfaces.
 */
export default function StoreLogo({ size = 'md' }: { size?: keyof typeof SIZES }) {
    const store = useStore();

    if (store.logo) {
        return <img src={store.logo} alt={store.name} className={`${SIZES[size]} object-cover`} />;
    }

    return (
        <span aria-hidden="true" className={`flex ${SIZES[size]} shrink-0 items-center justify-center bg-white`}>
            <svg viewBox="0 0 48 48" className="h-[86%] w-[86%]" fill="none" role="img">
                <path
                    d="M9 17h30l-2.6 22.1a4 4 0 0 1-4 3.6H15.6a4 4 0 0 1-4-3.6L9 17Z"
                    fill="#0e7a3d"
                />
                <path
                    d="M18 21v-2.5a6 6 0 0 1 12 0V21"
                    stroke="#ffffff"
                    strokeWidth="3.2"
                    strokeLinecap="round"
                />
                <path d="M18 28.5h12" stroke="#ffffff" strokeWidth="2.4" strokeLinecap="round" opacity="0.85" />
                <circle cx="35" cy="11" r="6.5" fill="#e11d2e" />
                <circle cx="35" cy="11" r="6.5" stroke="#ffffff" strokeWidth="2" />
            </svg>
        </span>
    );
}
