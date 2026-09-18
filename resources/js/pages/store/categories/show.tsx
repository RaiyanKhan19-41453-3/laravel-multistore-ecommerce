import Pagination from '@/components/store/pagination';
import ProductCard from '@/components/store/product-card';
import ProductGridSkeleton from '@/components/store/product-grid-skeleton';
import StoreLayout from '@/layouts/store-layout';
import { useT } from '@/lib/store';
import type { PaginatedData, ProductSummary } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface CategoryData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    parent: { id: number; name: string; slug: string } | null;
    children: { id: number; name: string; slug: string; children_count: number }[];
    products: PaginatedData<ProductSummary>;
}

export default function CategoryShow({ slug }: { slug: string }) {
    const t = useT();
    const [category, setCategory] = useState<CategoryData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        setLoading(true);
        void fetch(`/api/categories/${slug}`)
            .then((r) => {
                if (!r.ok) throw new Error();
                return r.json();
            })
            .then((json: { success: boolean; data: CategoryData }) => {
                if (json.success) setCategory(json.data);
                else setError('Category not found.');
            })
            .catch(() => setError('Category not found.'))
            .finally(() => setLoading(false));
    }, [slug]);

    if (loading) {
        return (
            <StoreLayout title="Category">
                <div className="store-container py-8">
                    <div className="mb-6 h-8 w-48 animate-pulse rounded bg-gray-200 dark:bg-neutral-700" />
                    <ProductGridSkeleton />
                </div>
            </StoreLayout>
        );
    }

    if (error || !category) {
        return (
            <StoreLayout title="Category">
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
        <StoreLayout title={category.name}>
            <Head>
                <title>{category.name}</title>
                {category.description && <meta name="description" content={category.description} />}
            </Head>

            <div className="store-container py-8">
                <nav className="mb-4 text-sm text-[var(--store-muted)]">
                    <Link href="/" className="hover:underline">
                        {t('store.home')}
                    </Link>
                    {category.parent && (
                        <>
                            <span className="mx-1">/</span>
                            <Link href={`/categories/${category.parent.slug}`} className="hover:underline">
                                {category.parent.name}
                            </Link>
                        </>
                    )}
                    <span className="mx-1">/</span>
                    <span className="text-[var(--store-text)]">{category.name}</span>
                </nav>

                <h1 className="mb-2 text-2xl font-bold">{category.name}</h1>
                {category.description && <p className="mb-6 text-[var(--store-muted)]">{category.description}</p>}

                {category.children.length > 0 && (
                    <div className="mb-8 flex flex-wrap gap-2">
                        {category.children.map((child) => (
                            <Link
                                key={child.id}
                                href={`/categories/${child.slug}`}
                                className="rounded-lg border border-[var(--store-border)] px-4 py-1.5 text-sm transition hover:border-[var(--store-accent)] hover:text-[var(--store-accent)]"
                            >
                                {child.name}
                            </Link>
                        ))}
                    </div>
                )}

                {category.products.data.length === 0 ? (
                    <p className="py-12 text-center text-[var(--store-muted)]">{t('store.no_products')}</p>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {category.products.data.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                )}

                <Pagination data={category.products} />
            </div>
        </StoreLayout>
    );
}
