import StoreLayout from '@/layouts/store-layout';
import { getGuestToken } from '@/lib/guest-token';
import type { ProductDetail, ProductVariant } from '@/types';
import { Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function formatPrice(value: number): string {
    return `৳${Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

function findVariant(variants: ProductVariant[], selected: Record<number, number>): ProductVariant | null {
    const attrIds = Object.keys(selected).map(Number);

    if (attrIds.length === 0) {
        return null;
    }

    return (
        variants.find((v) => {
            const vAttrIds = v.values.map((val) => val.attribute.id);

            if (vAttrIds.length !== attrIds.length) {
                return false;
            }

            return attrIds.every((attrId) => {
                const selectedVal = selected[attrId];
                const vVal = v.values.find((val) => val.attribute.id === attrId);

                return vVal?.id === selectedVal;
            });
        }) ?? null
    );
}

export default function ProductShow({ slug }: { slug: string }) {
    const [product, setProduct] = useState<ProductDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selected, setSelected] = useState<Record<number, number>>({});
    const [quantity, setQuantity] = useState(1);
    const [adding, setAdding] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);

    useEffect(() => {
        setLoading(true);
        setError(null);
        void fetch(`/api/products/${slug}`)
            .then((r) => {
                if (!r.ok) throw new Error('not found');
                return r.json();
            })
            .then((json: { success: boolean; data: ProductDetail }) => {
                if (json.success) setProduct(json.data);
                else setError('Product not found.');
            })
            .catch(() => setError('Product not found.'))
            .finally(() => setLoading(false));
    }, [slug]);

    const attributes = useMemo(() => {
        if (!product) return [];

        const map = new Map<number, { id: number; name: string; values: { id: number; value: string }[] }>();

        for (const variant of product.variants) {
            for (const val of variant.values) {
                if (!map.has(val.attribute.id)) {
                    map.set(val.attribute.id, { id: val.attribute.id, name: val.attribute.name, values: [] });
                }

                const entry = map.get(val.attribute.id)!;

                if (!entry.values.some((v) => v.id === val.id)) {
                    entry.values.push({ id: val.id, value: val.value });
                }
            }
        }

        return Array.from(map.values());
    }, [product]);

    const selectedVariant = useMemo(() => {
        if (!product || product.type !== 'variable') return null;
        return findVariant(product.variants, selected);
    }, [product, selected]);

    const stock = product?.type === 'variable' ? (selectedVariant?.inventory.available ?? 0) : (product?.inventory?.available ?? 0);
    const activeDiscount = useMemo(() => {
        if (!product) return null;
        if (product.type === 'variable' && selectedVariant && product.variant_discounts) {
            return product.variant_discounts[selectedVariant.id] ?? product.discount;
        }
        return product.discount;
    }, [product, selectedVariant]);

    const unitPrice = product?.type === 'variable' ? (selectedVariant?.price ?? product.price) : (product?.price ?? 0);

    const addToCart = () => {
        if (!product) return;

        if (product.type === 'variable' && !selectedVariant) {
            setNotice('Please select all options.');
            return;
        }

        setAdding(true);
        setNotice(null);

        const csrf = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/)?.[2];

        void fetch('/api/cart/items', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Guest-Token': getGuestToken(),
                ...(csrf ? { 'X-XSRF-TOKEN': decodeURIComponent(csrf) } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                product_id: product.id,
                product_variant_id: selectedVariant?.id,
                quantity,
            }),
        })
            .then((r) => r.json())
            .then((json: { success: boolean; message?: string }) => {
                if (json.success) {
                    setNotice('Added to cart.');
                } else {
                    setNotice(json.message ?? 'Could not add to cart.');
                }
            })
            .catch(() => setNotice('Could not add to cart.'))
            .finally(() => setAdding(false));
    };

    if (loading) {
        return (
            <StoreLayout title="Product">
                <div className="mx-auto max-w-6xl px-4 py-12 text-[var(--store-muted)]">Loading...</div>
            </StoreLayout>
        );
    }

    if (error || !product) {
        return (
            <StoreLayout title="Product">
                <div className="mx-auto max-w-6xl px-4 py-12">
                    <p className="text-[var(--store-muted)]">{error ?? 'Product not found.'}</p>
                    <Link href="/" className="mt-2 inline-block text-sm text-[var(--store-accent)] hover:underline">
                        Back to home
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    return (
        <StoreLayout title={product.name}>
            <div className="mx-auto max-w-6xl px-4 py-6">
                <Link href="/" className="mb-4 inline-block text-sm text-[var(--store-muted)] hover:underline">
                    ← Back to home
                </Link>

                <div className="grid gap-8 md:grid-cols-2">
                    {/* Images */}
                    <div>
                        <div className="aspect-square overflow-hidden rounded-lg border border-[var(--store-border)] bg-gray-100 dark:bg-neutral-800">
                            {product.primary_image ? (
                                <img src={product.primary_image} alt={product.name} className="h-full w-full object-cover" />
                            ) : (
                                <div className="flex h-full w-full items-center justify-center text-[var(--store-muted)]">No image</div>
                            )}
                        </div>
                        {product.images.length > 1 && (
                            <div className="mt-3 flex gap-2">
                                {product.images.map((img) => (
                                    <div
                                        key={img.id}
                                        className="h-16 w-16 overflow-hidden rounded border border-[var(--store-border)]"
                                    >
                                        <img src={img.url} alt={img.alt_text ?? ''} className="h-full w-full object-cover" />
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Details */}
                    <div className="flex flex-col gap-4">
                        {product.brand && <p className="text-sm text-[var(--store-muted)]">{product.brand.name}</p>}

                        <h1 className="text-2xl font-bold">{product.name}</h1>

                        {/* Price */}
                        <div className="flex items-baseline gap-2">
                            <span className="text-2xl font-bold">{formatPrice(unitPrice)}</span>
                            {product.compare_at_price && (
                                <span className="text-sm text-[var(--store-muted)] line-through">{formatPrice(product.compare_at_price)}</span>
                            )}
                            {activeDiscount && (
                                <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900 dark:text-green-300">
                                    {activeDiscount.type === 'percentage' ? `${activeDiscount.value}% off` : `${formatPrice(activeDiscount.value)} off`}
                                </span>
                            )}
                        </div>

                        {/* Description */}
                        {product.short_description && (
                            <p className="text-sm text-[var(--store-muted)]">{product.short_description}</p>
                        )}
                        {product.description && (
                            <div className="text-sm leading-relaxed text-[var(--store-text)]">{product.description}</div>
                        )}

                        {/* Categories */}
                        {product.categories.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {product.categories.map((cat) => (
                                    <span
                                        key={cat.id}
                                        className="rounded-full border border-[var(--store-border)] px-3 py-0.5 text-xs text-[var(--store-muted)]"
                                    >
                                        {cat.name}
                                    </span>
                                ))}
                            </div>
                        )}

                        {/* Variant selectors */}
                        {attributes.map((attr) => (
                            <div key={attr.id}>
                                <p className="mb-2 text-sm font-medium">{attr.name}</p>
                                <div className="flex flex-wrap gap-2">
                                    {attr.values.map((val) => {
                                        const isSelected = selected[attr.id] === val.id;
                                        return (
                                            <button
                                                key={val.id}
                                                type="button"
                                                onClick={() => setSelected((prev) => ({ ...prev, [attr.id]: val.id }))}
                                                className={`rounded-md border px-4 py-2 text-sm transition ${
                                                    isSelected
                                                        ? 'border-[var(--store-accent)] bg-[var(--store-accent)] text-white'
                                                        : 'border-[var(--store-border)] hover:border-[var(--store-accent)]'
                                                }`}
                                            >
                                                {val.value}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}

                        {/* Stock */}
                        <p className="text-sm text-[var(--store-muted)]">
                            {stock > 0 ? `${stock} in stock` : 'Out of stock'}
                        </p>

                        {/* Quantity + Add to cart */}
                        <div className="flex items-center gap-3">
                            <label className="text-sm font-medium">Qty</label>
                            <input
                                type="number"
                                min={1}
                                max={stock}
                                value={quantity}
                                onChange={(e) => setQuantity(Math.max(1, Math.min(stock, Number(e.target.value))))}
                                className="w-20 rounded-md border border-[var(--store-border)] px-3 py-2 text-sm"
                            />
                        </div>

                        <button
                            type="button"
                            onClick={addToCart}
                            disabled={adding || stock <= 0}
                            className="rounded-lg bg-[var(--store-accent)] px-6 py-3 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
                        >
                            {adding ? 'Adding...' : 'Add to cart'}
                        </button>

                        {notice && (
                            <p className={`text-sm ${notice.includes('Added') ? 'text-green-600' : 'text-red-600'}`}>{notice}</p>
                        )}
                    </div>
                </div>
            </div>
        </StoreLayout>
    );
}
