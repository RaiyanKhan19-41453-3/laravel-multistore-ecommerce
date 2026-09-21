import Pagination from '@/components/store/pagination';
import ProductCard from '@/components/store/product-card';
import StoreLayout from '@/layouts/store-layout';
import { useT } from '@/lib/store';
import type { PaginatedData, ProductSummary } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface BrandData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    products: PaginatedData<ProductSummary>;
}

export default function BrandShow({ brand }: { brand: BrandData | null }) {
    const t = useT();

    if (!brand) {
        return (
            <StoreLayout title="Brand">
                <div className="store-container py-12">
                    <p className="text-[var(--store-muted)]">{t('store.error_loading')}</p>
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
