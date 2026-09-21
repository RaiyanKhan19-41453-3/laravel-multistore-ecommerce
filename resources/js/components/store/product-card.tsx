import type { ProductSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { Star } from 'lucide-react';
import Price from './price';
import ProductImage from './product-image';

export default function ProductCard({ product, compact = false }: { product: ProductSummary; compact?: boolean }) {
    const discountPercent =
        product.compare_at_price && product.compare_at_price > product.price
            ? Math.round((1 - product.price / product.compare_at_price) * 100)
            : 0;

    return (
        <Link href={`/products/${product.slug}`} prefetch className="group flex h-full flex-col">
            <div className="relative aspect-square overflow-hidden rounded-xl bg-[var(--store-card-hover)]">
                <div className="h-full w-full transition duration-500 group-hover:scale-105">
                    <ProductImage src={product.primary_image} seed={product.id} alt={product.name} />
                </div>
            </div>
            <div className={`flex flex-1 flex-col ${compact ? 'gap-0.5 px-0.5 pt-1.5' : 'gap-1 px-0.5 pt-2.5'}`}>
                <h2 className={`line-clamp-2 leading-snug font-normal text-[var(--store-text)] ${compact ? 'min-h-8 text-xs' : 'min-h-10 text-sm'}`}>
                    {product.name}
                </h2>
                <div className="mt-auto flex flex-wrap items-baseline gap-x-2 pt-0.5">
                    <Price value={product.price} compareAt={product.compare_at_price} size={compact ? 'sm' : 'md'} />
                    {discountPercent > 0 && (
                        <span className="text-xs font-bold text-[var(--store-deal)]">-{discountPercent}%</span>
                    )}
                </div>
                {product.review_summary && product.review_summary.total > 0 && (
                    <div className="flex items-center gap-1 text-[11px] text-[var(--store-muted)]">
                        <Star className="h-3 w-3 fill-[var(--store-star)] text-[var(--store-star)]" />
                        <span className="font-semibold">{product.review_summary.average.toFixed(1)}</span>
                        <span>({product.review_summary.total})</span>
                    </div>
                )}
            </div>
        </Link>
    );
}
