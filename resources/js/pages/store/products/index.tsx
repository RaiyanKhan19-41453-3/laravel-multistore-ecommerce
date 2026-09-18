import Pagination from '@/components/store/pagination';
import ProductCard from '@/components/store/product-card';
import StoreLayout from '@/layouts/store-layout';
import { useT } from '@/lib/store';
import type { PaginatedData, ProductSummary } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

interface Filters {
    search?: string;
    min_price?: string;
    max_price?: string;
    sort?: string;
    direction?: string;
    brand_id?: string;
    category_id?: string;
    attribute_values?: string;
}

export default function StoreProducts() {
    const t = useT();
    const pageProps = usePage<{
        products: PaginatedData<ProductSummary>;
        filters?: Filters;
        brands?: { id: number; name: string; slug: string }[];
        categories?: { id: number; name: string; slug: string }[];
    }>().props;

    const rawFilters = pageProps.filters ?? {};
    // Guard against PHP [] serializing to a JS array: [].sort is
    // Array.prototype.sort, which useState() would call as an initializer.
    const serverFilters: Filters = Array.isArray(rawFilters) ? {} : rawFilters;
    const products = pageProps.products ?? { data: [], links: [], meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 } };
    const brands = pageProps.brands ?? [];
    const categories = pageProps.categories ?? [];

    const [search, setSearch] = useState(serverFilters.search ?? '');
    const [minPrice, setMinPrice] = useState(serverFilters.min_price ?? '');
    const [maxPrice, setMaxPrice] = useState(serverFilters.max_price ?? '');
    const [sort, setSort] = useState(serverFilters.sort ?? 'name');
    const [direction, setDirection] = useState(serverFilters.direction ?? 'asc');
    const [brandId, setBrandId] = useState(serverFilters.brand_id ?? '');
    const [categoryId, setCategoryId] = useState(serverFilters.category_id ?? '');

    const applyFilters = useCallback(
        (overrides: Partial<Filters> = {}) => {
            const params: Filters = {
                search: overrides.search !== undefined ? overrides.search : search,
                min_price: overrides.min_price !== undefined ? overrides.min_price : minPrice,
                max_price: overrides.max_price !== undefined ? overrides.max_price : maxPrice,
                sort: overrides.sort !== undefined ? overrides.sort : sort,
                direction: overrides.direction !== undefined ? overrides.direction : direction,
                brand_id: overrides.brand_id !== undefined ? overrides.brand_id : brandId,
                category_id: overrides.category_id !== undefined ? overrides.category_id : categoryId,
            };

            const clean: Record<string, string> = {};
            Object.entries(params).forEach(([k, v]) => {
                if (v) clean[k] = v;
            });

            router.get('/products', clean, { preserveState: true, replace: true });
        },
        [search, minPrice, maxPrice, sort, direction, brandId, categoryId],
    );

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (serverFilters.search ?? '')) {
                applyFilters({ search });
            }
        }, 400);
        return () => clearTimeout(timer);
    }, [search, serverFilters.search, applyFilters]);

    const handlePageChange = (url: string) => {
        router.get(url, {}, { preserveState: true, replace: true });
    };

    return (
        <StoreLayout title={t('store.products')}>
            <div className="store-container py-8">
                <div className="mb-8">
                    <p className="mb-1 text-xs font-bold tracking-[0.18em] text-[var(--store-accent)] uppercase">{t('store.products')}</p>
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">{t('store.products')}</h1>
                        <p className="text-sm text-[var(--store-muted)]">
                            {products.total} {t('store.products').toLowerCase()}
                        </p>
                    </div>
                    {categories.length > 0 && (
                        <div className="-mx-4 mt-5 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <button
                                type="button"
                                onClick={() => {
                                    setCategoryId('');
                                    applyFilters({ category_id: '' });
                                }}
                                className={`shrink-0 rounded-lg border px-4 py-1.5 text-sm font-semibold whitespace-nowrap transition ${
                                    !categoryId
                                        ? 'border-[var(--store-text)] bg-[var(--store-text)] text-[var(--store-bg)]'
                                        : 'border-[var(--store-border)] hover:border-[var(--store-text)]'
                                }`}
                            >
                                {t('store.all_categories')}
                            </button>
                            {categories.map((cat) => (
                                <button
                                    key={cat.id}
                                    type="button"
                                    onClick={() => {
                                        const next = String(cat.id);
                                        setCategoryId(next);
                                        applyFilters({ category_id: next });
                                    }}
                                    className={`shrink-0 rounded-lg border px-4 py-1.5 text-sm font-semibold whitespace-nowrap transition ${
                                        categoryId === String(cat.id)
                                            ? 'border-[var(--store-text)] bg-[var(--store-text)] text-[var(--store-bg)]'
                                            : 'border-[var(--store-border)] hover:border-[var(--store-text)]'
                                    }`}
                                >
                                    {cat.name}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-[260px_1fr]">
                    {/* Filters Sidebar */}
                    <aside className="h-fit space-y-5 rounded-[var(--store-radius)] border border-[var(--store-border)] bg-[var(--store-card)] p-5 lg:sticky lg:top-24">
                        {/* Search */}
                        <div>
                            <label className="mb-1 block text-sm font-medium">{t('store.search')}</label>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('store.search')}
                                className="w-full rounded-lg border border-[var(--store-border)] bg-[var(--store-input)] px-3 py-2 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                            />
                        </div>

                        {/* Category */}
                        <div>
                            <label className="mb-1 block text-sm font-medium">{t('store.categories')}</label>
                            <select
                                value={categoryId}
                                onChange={(e) => {
                                    setCategoryId(e.target.value);
                                    applyFilters({ category_id: e.target.value });
                                }}
                                className="w-full rounded-lg border border-[var(--store-border)] bg-[var(--store-input)] px-3 py-2 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                            >
                                <option value="">{t('store.all_categories')}</option>
                                {categories.map((cat) => (
                                    <option key={cat.id} value={cat.id}>
                                        {cat.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Brand */}
                        <div>
                            <label className="mb-1 block text-sm font-medium">{t('store.brands')}</label>
                            <select
                                value={brandId}
                                onChange={(e) => {
                                    setBrandId(e.target.value);
                                    applyFilters({ brand_id: e.target.value });
                                }}
                                className="w-full rounded-lg border border-[var(--store-border)] bg-[var(--store-input)] px-3 py-2 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                            >
                                <option value="">{t('store.all_brands')}</option>
                                {brands.map((brand) => (
                                    <option key={brand.id} value={brand.id}>
                                        {brand.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Price Range */}
                        <div>
                            <label className="mb-1 block text-sm font-medium">Price Range</label>
                            <div className="flex gap-2">
                                <input
                                    type="number"
                                    placeholder="Min"
                                    value={minPrice}
                                    onChange={(e) => setMinPrice(e.target.value)}
                                    className="w-full rounded-lg border border-[var(--store-border)] bg-[var(--store-input)] px-3 py-2 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                />
                                <input
                                    type="number"
                                    placeholder="Max"
                                    value={maxPrice}
                                    onChange={(e) => setMaxPrice(e.target.value)}
                                    className="w-full rounded-lg border border-[var(--store-border)] bg-[var(--store-input)] px-3 py-2 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                                />
                            </div>
                            <button
                                type="button"
                                onClick={() => applyFilters()}
                                className="mt-2 w-full rounded-md bg-[var(--store-accent)] px-3 py-2 text-sm text-white hover:opacity-90"
                            >
                                Apply
                            </button>
                        </div>

                        {/* Sort */}
                        <div>
                            <label className="mb-1 block text-sm font-medium">{t('store.sort_by')}</label>
                            <select
                                value={`${sort}_${direction}`}
                                onChange={(e) => {
                                    const [s, d] = e.target.value.split('_');
                                    setSort(s);
                                    setDirection(d);
                                    applyFilters({ sort: s, direction: d });
                                }}
                                className="w-full rounded-lg border border-[var(--store-border)] bg-[var(--store-input)] px-3 py-2 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)]"
                            >
                                <option value="name_asc">{t('store.products')}</option>
                                <option value="price_asc">{t('store.price_low_high')}</option>
                                <option value="price_desc">{t('store.price_high_low')}</option>
                                <option value="created_at_desc">{t('store.newest')}</option>
                                <option value="rating_desc">{t('store.rating_sort')}</option>
                            </select>
                        </div>

                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                setMinPrice('');
                                setMaxPrice('');
                                setSort('name');
                                setDirection('asc');
                                setBrandId('');
                                setCategoryId('');
                                applyFilters({
                                    search: '',
                                    min_price: '',
                                    max_price: '',
                                    sort: 'name',
                                    direction: 'asc',
                                    brand_id: '',
                                    category_id: '',
                                });
                            }}
                            className="w-full rounded-lg border border-[var(--store-border)] bg-[var(--store-input)] px-3 py-2 text-sm outline-none transition placeholder:text-[var(--store-muted)] focus:border-[var(--store-accent)] hover:bg-[var(--store-card)]"
                        >
                            {t('store.clear_filters')}
                        </button>
                    </aside>

                    {/* Products Grid */}
                    <div>
                        {products.data.length === 0 ? (
                            <div className="rounded-[var(--store-radius)] border border-dashed border-[var(--store-border)] py-16 text-center">
                                <p className="text-[var(--store-muted)]">{t('store.no_products')}</p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                {products.data.map((product) => (
                                    <ProductCard key={product.id} product={product} />
                                ))}
                            </div>
                        )}

                        <Pagination data={products} onPageChange={handlePageChange} />
                    </div>
                </div>
            </div>
        </StoreLayout>
    );
}
