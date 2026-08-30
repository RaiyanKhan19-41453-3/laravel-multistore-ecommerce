import StoreLayout from '@/layouts/store-layout';
import type { ProductSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function formatPrice(value: number): string {
    return `৳${Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

export default function StoreProducts() {
    const [products, setProducts] = useState<ProductSummary[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void fetch('/api/products?per_page=50')
            .then((r) => r.json())
            .then((json: { success: boolean; data: { data: ProductSummary[] } }) => {
                if (json.success) setProducts(json.data.data);
            })
            .finally(() => setLoading(false));
    }, []);

    return (
        <StoreLayout title="Products">
            <div className="mx-auto max-w-6xl px-4 py-8">
                <h1 className="mb-6 text-2xl font-bold">All Products</h1>

                {loading ? (
                    <p className="text-[var(--store-muted)]">Loading...</p>
                ) : products.length === 0 ? (
                    <p className="text-[var(--store-muted)]">No products found.</p>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {products.map((product) => (
                            <Link
                                key={product.id}
                                href={`/products/${product.slug}`}
                                className="group overflow-hidden rounded-lg border border-[var(--store-border)] transition hover:shadow-md"
                            >
                                <div className="aspect-square overflow-hidden bg-gray-100">
                                    {product.primary_image ? (
                                        <img
                                            src={product.primary_image}
                                            alt={product.name}
                                            className="h-full w-full object-cover transition group-hover:scale-105"
                                        />
                                    ) : (
                                        <div className="flex h-full w-full items-center justify-center text-sm text-[var(--store-muted)]">
                                            No image
                                        </div>
                                    )}
                                </div>
                                <div className="p-3">
                                    {product.brand && (
                                        <p className="text-xs text-[var(--store-muted)]">{product.brand.name}</p>
                                    )}
                                    <h2 className="truncate text-sm font-medium">{product.name}</h2>
                                    <div className="mt-1 flex items-baseline gap-2">
                                        <span className="text-sm font-bold">{formatPrice(product.price)}</span>
                                        {product.compare_at_price && (
                                            <span className="text-xs text-[var(--store-muted)] line-through">
                                                {formatPrice(product.compare_at_price)}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </StoreLayout>
    );
}
