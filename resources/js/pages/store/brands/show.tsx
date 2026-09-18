import Pagination from '@/components/store/pagination';
import ProductCard from '@/components/store/product-card';
import ProductGridSkeleton from '@/components/store/product-grid-skeleton';
import StoreLayout from '@/layouts/store-layout';
import { useT } from '@/lib/store';
import type { PaginatedData, ProductSummary } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface BrandData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    products: PaginatedData<ProductSummary>;
}

export default function BrandShow({ slug }: { slug: string }) {
    const t = useT();
    const [brand, setBrand] = useState<BrandData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        setLoading(true);
        void fetch(`/api/brands/${slug}`)
            .then((r) => {
                if (!r.ok) throw new Error();
                return r.json();
            })
            .then((json: { success: boolean; data: BrandData }) => {
                if (json.success) setBrand(json.data);
                else setError('Brand not found.');
            })
            .catch(() => setError('Brand not found.'))
            .finally(() => setLoading(false));
    }, [slug]);

    if (loading) {
        return (
            <StoreLayout title="Brand">
                <div className="store-container py-8">
                    <div className="mb-6 h-8 w-48 animate-pulse rounded bg-gray-200 dark:bg-neutral-700" />
                    <ProductGridSkeleton />
                </div>
            </StoreLayout>
        );
    }

    if (error || !brand) {
        return (
            <StoreLayout title="Brand">
                <div className="store-container py-12">
                    <p className="text-[var(--store-muted)]">{error ?? 'Not found.'}</p>
                    <Link href="/" className="mt-2 inline-block text-sm text-[var(--store-accent)] hover:underline">
                        {t('store.back_to_home')}
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    return (
        <StoreLayout title={brand.name}>
            <Head>
                <title>{brand.name}</title>
                {brand.description && <meta name="description" content={brand.description} />}
            </Head>

            <div className="store-container py-8">
                <nav className="mb-4 text-sm text-[var(--store-muted)]">
                    <Link href="/" className="hover:underline">
                        {t('store.home')}
                    </Link>
                    <span className="mx-1">/</span>
                    <Link href="/products" className="hover:underline">
                        {t('store.products')}
                    </Link>
                    <span className="mx-1">/</span>
                    <span className="text-[var(--store-text)]">{brand.name}</span>
                </nav>

                <h1 className="mb-2 text-2xl font-bold">{brand.name}</h1>
                {brand.description && <p className="mb-6 text-[var(--store-muted)]">{brand.description}</p>}

                {brand.products.data.length === 0 ? (
                    <p className="py-12 text-center text-[var(--store-muted)]">{t('store.no_products')}</p>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {brand.products.data.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                )}

                <Pagination data={brand.products} />
            </div>
        </StoreLayout>
    );
}
