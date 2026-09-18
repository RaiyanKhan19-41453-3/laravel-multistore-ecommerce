import Pagination from '@/components/store/pagination';
import ProductCard from '@/components/store/product-card';
import ProductGridSkeleton from '@/components/store/product-grid-skeleton';
import StoreLayout from '@/layouts/store-layout';
import { useT } from '@/lib/store';
import type { PaginatedData, ProductSummary } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

export default function StoreSearch() {
    const t = useT();
    const [query, setQuery] = useState('');
    const [products, setProducts] = useState<PaginatedData<ProductSummary> | null>(null);
    const [loading, setLoading] = useState(false);

    const search = useCallback((q: string, page = 1) => {
        if (q.length < 2) {
            setProducts(null);
            return;
        }
        setLoading(true);
        void fetch(`/api/products?search=${encodeURIComponent(q)}&page=${page}&per_page=20`)
            .then((r) => r.json())
            .then((json: { success: boolean; data: PaginatedData<ProductSummary> }) => {
                if (json.success) setProducts(json.data);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const q = params.get('q') ?? '';
        if (q) {
            setQuery(q);
            search(q);
        }
    }, [search]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (query.length >= 2) {
                router.get('/search', { q: query }, { preserveState: true, replace: true });
                search(query);
            } else {
                setProducts(null);
            }
        }, 400);
        return () => clearTimeout(timer);
    }, [query, search]);

    const handlePageChange = (url: string) => {
        const page = new URL(url).searchParams.get('page') ?? '1';
        search(query, Number(page));
    };

    return (
        <StoreLayout title={t('store.search')}>
            <Head>
                <title>{query ? `${query} — ${t('store.search')}` : t('store.search')}</title>
            </Head>

            <div className="store-container py-8">
                <p className="mb-1 text-xs font-bold tracking-[0.18em] text-[var(--store-accent)] uppercase">{t('store.search')}</p>
                <h1 className="text-2xl font-bold tracking-tight md:text-3xl">{t('store.search')}</h1>

                <div className="relative mx-auto mt-6 mb-8 max-w-2xl">
                    <Search className="absolute top-1/2 start-4 h-5 w-5 -translate-y-1/2 text-[var(--store-muted)]" />
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('store.search')}
                        className="w-full rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] py-3.5 pe-4 ps-12 text-sm shadow-[var(--store-shadow)] outline-none placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                        autoFocus
                    />
                </div>

                {loading ? (
                    <ProductGridSkeleton />
                ) : query.length < 2 ? (
                    <p className="text-[var(--store-muted)]">Type at least 2 characters to search.</p>
                ) : !products || products.data.length === 0 ? (
                    <p className="py-12 text-center text-[var(--store-muted)]">{t('store.no_products')}</p>
                ) : (
                    <>
                        <p className="mb-4 text-sm text-[var(--store-muted)]">
                            {products.total} result{products.total !== 1 ? 's' : ''} for &quot;{query}&quot;
                        </p>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                            {products.data.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                        <Pagination data={products} onPageChange={handlePageChange} />
                    </>
                )}
            </div>
        </StoreLayout>
    );
}
