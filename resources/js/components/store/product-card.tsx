import type { ProductSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { Eye, Star } from 'lucide-react';
import Price from './price';
import ProductImage from './product-image';
import { openQuickView } from './quick-view';
import { useT } from '@/lib/store';

export default function ProductCard({ product, compact = false }: { product: ProductSummary; compact?: boolean }) {
    const t = useT();
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
                <button
                    type="button"
                    title={t('store.quick_view')}
                    aria-label={`${t('store.quick_view')}: ${product.name}`}
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        openQuickView(product);
                    }}
                    className="absolute end-2 top-2 flex h-9 w-9 items-center justify-center rounded-full bg-[var(--store-card)] text-[var(--store-muted)] opacity-100 shadow-md transition hover:text-[var(--store-accent)] sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
                >
                    <Eye className="h-4 w-4" />
                </button>
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
