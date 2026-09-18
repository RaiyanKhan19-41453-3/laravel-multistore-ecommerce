import type { ProductSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { Star } from 'lucide-react';
import Price from './price';
import ProductImage from './product-image';

export default function ProductCard({ product }: { product: ProductSummary }) {
    const discountPercent =
        product.compare_at_price && product.compare_at_price > product.price
            ? Math.round((1 - product.price / product.compare_at_price) * 100)
            : 0;

    return (
        <Link
            href={`/products/${product.slug}`}
            prefetch
            className="group flex flex-col transition duration-300 hover:-translate-y-1"
        >
            <div className="relative aspect-square overflow-hidden rounded-xl bg-[var(--store-card-hover)] transition-shadow duration-300 group-hover:shadow-[var(--store-shadow)]">
                <div className="h-full w-full transition duration-500 group-hover:scale-105">
                    <ProductImage src={product.primary_image} seed={product.id} alt={product.name} />
                </div>
                {discountPercent > 0 && (
                    <span className="absolute top-3 start-3 rounded-lg bg-red-600 px-2.5 py-1 text-[11px] font-bold text-white shadow">
                        -{discountPercent}%
                    </span>
                )}
                <span className="absolute inset-x-3 bottom-3 translate-y-2 rounded-lg bg-black/65 py-2 text-center text-xs font-bold text-white opacity-0 backdrop-blur transition duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                    View product
                </span>
            </div>
            <div className="flex flex-1 flex-col gap-1 px-1 pt-3">
                {product.brand && (
                    <p className="text-[11px] font-bold tracking-[0.14em] text-[var(--store-accent)] uppercase">{product.brand.name}</p>
                )}
                <h2 className="line-clamp-2 text-sm leading-snug font-semibold text-[var(--store-text)]">
                    {product.name}
                </h2>
                {product.review_summary && product.review_summary.total > 0 && (
                    <div className="flex items-center gap-1.5 text-xs text-[var(--store-muted)]">
                        <Star className="h-3.5 w-3.5 fill-[var(--store-star)] text-[var(--store-star)]" />
                        <span className="font-semibold text-[var(--store-text)]">{product.review_summary.average.toFixed(1)}</span>
                        <span>({product.review_summary.total})</span>
                    </div>
                )}
                <div className="mt-auto pt-2">
                    <Price value={product.price} compareAt={product.compare_at_price} />
                </div>
            </div>
        </Link>
    );
}
