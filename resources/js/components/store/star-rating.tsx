import { Star } from 'lucide-react';
import { useState } from 'react';

export default function StarRating({
    value,
    onChange,
    readonly = false,
    size = 'md',
}: {
    value: number;
    onChange?: (rating: number) => void;
    readonly?: boolean;
    size?: 'sm' | 'md' | 'lg';
}) {
    const [hover, setHover] = useState(0);

    const iconClass = size === 'lg' ? 'h-7 w-7' : size === 'sm' ? 'h-3.5 w-3.5' : 'h-5 w-5';

    return (
        <div className="flex items-center gap-0.5" role={readonly ? 'img' : 'radiogroup'} aria-label={`Rated ${value} out of 5`}>
            {[1, 2, 3, 4, 5].map((star) => {
                const active = star <= (hover || value);

                return (
                    <button
                        key={star}
                        type="button"
                        disabled={readonly}
                        onClick={() => onChange?.(star)}
                        onMouseEnter={() => !readonly && setHover(star)}
                        onMouseLeave={() => !readonly && setHover(0)}
                        aria-label={`${star} star${star > 1 ? 's' : ''}`}
                        className={`${readonly ? 'cursor-default' : 'cursor-pointer transition-transform hover:scale-110'}`}
                    >
                        <Star
                            className={`${iconClass} ${
                                active ? 'fill-[var(--store-star)] text-[var(--store-star)]' : 'fill-transparent text-[var(--store-border)]'
                            }`}
                        />
                    </button>
                );
            })}
        </div>
    );
}
