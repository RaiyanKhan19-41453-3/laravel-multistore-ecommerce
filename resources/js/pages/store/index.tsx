import StoreLayout from '@/layouts/store-layout';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface FeaturedProduct {
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    price: number;
    compare_at_price: number | null;
    brand: { id: number; name: string; slug: string } | null;
    primary_image: string | null;
    variants_count: number;
}

function formatPrice(value: number): string {
    return `৳${Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

export default function StoreIndex() {
    const [featured, setFeatured] = useState<FeaturedProduct[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void fetch('/api/products/featured')
            .then((r) => r.json())
            .then((json: { success: boolean; data: FeaturedProduct[] }) => {
                if (json.success) {
                    setFeatured(json.data);
                }
            })
            .finally(() => setLoading(false));
    }, []);

    return (
        <StoreLayout title="Home">
            {/* Hero */}
            <section className="flex flex-col items-center justify-center bg-gradient-to-br from-[var(--store-accent)] to-blue-700 px-4 py-20 text-center text-white">
                <h1 className="mb-4 text-4xl font-bold md:text-5xl">Welcome to Our Store</h1>
                <p className="mb-8 max-w-xl text-lg text-blue-100">
                    Discover top brands and the latest trends. Shop sneakers, clothing, and accessories — all in one place.
                </p>
                <a
                    href="#featured"
                    className="rounded-lg bg-white px-6 py-3 text-sm font-semibold text-[var(--store-accent)] shadow hover:bg-blue-50"
                >
                    Shop Now
                </a>
            </section>

            {/* Featured Products */}
            <section id="featured" className="mx-auto max-w-6xl px-4 py-12">
                <h2 className="mb-6 text-2xl font-semibold">Featured Products</h2>

                {loading ? (
                    <p className="text-[var(--store-muted)]">Loading products...</p>
                ) : featured.length === 0 ? (
                    <p className="text-[var(--store-muted)]">No featured products yet.</p>
                ) : (
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {featured.map((product) => (
                            <Link
                                key={product.id}
                                href={`/products/${product.slug}`}
                                className="group overflow-hidden rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] transition hover:shadow-md"
                            >
                                <div className="aspect-[4/3] bg-gray-100 dark:bg-neutral-800">
                                    {product.primary_image ? (
                                        <img
                                            src={product.primary_image}
                                            alt={product.name}
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-full w-full items-center text-[var(--store-muted)]">No image</div>
                                    )}
                                </div>
                                <div className="p-4">
                                    <p className="mb-1 text-xs text-[var(--store-muted)]">{product.brand?.name}</p>
                                    <h3 className="group-hover:underline">{product.name}</h3>
                                    <div className="mt-2 flex items-baseline gap-2">
                                        <span className="font-semibold">{formatPrice(product.price)}</span>
                                        {product.compare_at_price && (
                                            <span className="text-sm text-[var(--store-muted)] line-through">
                                                {formatPrice(product.compare_at_price)}
                                            </span>
                                        )}
                                    </div>
                                    {product.variants_count > 0 && (
                                        <p className="mt-1 text-xs text-[var(--store-muted)]">
                                            {product.variants_count} variant{product.variants_count > 1 ? 's' : ''}
                                        </p>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </section>
        </StoreLayout>
    );
}
