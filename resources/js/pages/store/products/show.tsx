import Price from '@/components/store/price';
import QuantityStepper from '@/components/store/quantity-stepper';
import StarRating from '@/components/store/star-rating';
import StoreButton from '@/components/store/store-button';
import StoreLayout from '@/layouts/store-layout';
import { apiStore, getUser } from '@/lib/auth';
import { formatPrice } from '@/lib/format';
import { useT } from '@/lib/store';
import type { ProductDetail, ProductVariant, Review, ReviewSummary } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { BadgeCheck, Banknote, Check, Heart, MapPin, RotateCcw, ShieldCheck, ShoppingBag, Truck } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

function findVariant(variants: ProductVariant[], selected: Record<number, number>): ProductVariant | null {
    const attrIds = Object.keys(selected).map(Number);

    if (attrIds.length === 0) return null;

    return (
        variants.find((v) => {
            const vAttrIds = v.values.map((val) => val.attribute.id);
            if (vAttrIds.length !== attrIds.length) return false;
            return attrIds.every((attrId) => {
                const selectedVal = selected[attrId];
                const vVal = v.values.find((val) => val.attribute.id === attrId);
                return vVal?.id === selectedVal;
            });
        }) ?? null
    );
}

export default function ProductShow({ slug, product: initialProduct }: { slug: string; product: ProductDetail | null }) {
    const t = useT();
    const [product] = useState<ProductDetail | null>(initialProduct);
    const [selected, setSelected] = useState<Record<number, number>>({});
    const [quantity, setQuantity] = useState(1);
    const [adding, setAdding] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const [selectedImage, setSelectedImage] = useState<string | null>(null);
    const [wishlisted, setWishlisted] = useState(false);

    // Reviews state
    const [reviews, setReviews] = useState<Review[]>([]);
    const [reviewSummary, setReviewSummary] = useState<ReviewSummary | null>(null);
    const [reviewPage, setReviewPage] = useState(1);
    const [reviewTotalPages, setReviewTotalPages] = useState(1);
    const [showReviewForm, setShowReviewForm] = useState(false);
    const [reviewRating, setReviewRating] = useState(5);
    const [reviewTitle, setReviewTitle] = useState('');
    const [reviewBody, setReviewBody] = useState('');
    const [submittingReview, setSubmittingReview] = useState(false);
    const [reviewNotice, setReviewNotice] = useState<string | null>(null);
    const [guestName, setGuestName] = useState('');
    const [guestEmail, setGuestEmail] = useState('');
    const [guestOrderNumber, setGuestOrderNumber] = useState('');
    const isGuestReviewer = getUser() === null;

    // Load reviews
    useEffect(() => {
        if (!slug) return;
        void fetch(`/api/products/${slug}/reviews?page=${reviewPage}`)
            .then((r) => r.json())
            .then((json: { success: boolean; data: { reviews: { data: Review[]; last_page: number }; summary: ReviewSummary } }) => {
                if (json.success) {
                    setReviews(json.data.reviews.data);
                    setReviewSummary(json.data.summary);
                    setReviewTotalPages(json.data.reviews.last_page);
                }
            })
            .catch(() => {});
    }, [slug, reviewPage]);

    // Check wishlist
    useEffect(() => {
        if (!product) return;
        void apiStore<number[]>(`/wishlist/check?product_ids[]=${product.id}`).then((res) => {
            if (res.ok && res.data) {
                setWishlisted(res.data.includes(product.id));
            }
        });
    }, [product]);

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

    // Gallery follows the selection: any picked option narrows the
    // variants, and only matching variants' shots show. Nothing picked
    // (or no match) falls back to the product images.
    const galleryImages = useMemo(() => {
        if (product && product.type === 'variable') {
            const picked = Object.entries(selected);
            if (picked.length > 0) {
                const seen = new Set<number>();
                const matched: { id: number; url: string; alt_text: string | null }[] = [];
                for (const variant of product.variants) {
                    const matches = picked.every(
                        ([attrId, valId]) =>
                            variant.values.some((val) => val.attribute.id === Number(attrId) && val.id === valId),
                    );
                    if (!matches) continue;
                    for (const img of variant.images ?? []) {
                        if (!seen.has(img.id)) {
                            seen.add(img.id);
                            matched.push({ id: img.id, url: img.url, alt_text: img.alt_text });
                        }
                    }
                }
                if (matched.length > 0) return matched;
            }
        }
        return (product?.images ?? []).map((img) => ({ id: img.id, url: img.url, alt_text: img.alt_text }));
    }, [product, selected]);

    useEffect(() => {
        setSelectedImage(null);
    }, [selectedVariant?.id]);

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

        void apiStore('/cart/items', {
            body: { product_id: product.id, product_variant_id: selectedVariant?.id, quantity },
        })
            .then((res) => {
                setAdding(false);
                if (res.ok) {
                    setNotice('Added to cart.');
                    window.dispatchEvent(new CustomEvent('cart:updated'));
                    window.dispatchEvent(new CustomEvent('cart:open'));
                } else {
                    setNotice(res.message ?? 'Could not add to cart.');
                }
            })
            .catch(() => {
                setAdding(false);
                setNotice('Could not add to cart.');
            });
    };

    const toggleWishlist = () => {
        if (!product) return;
        void apiStore<{ wishlisted: boolean }>(`/wishlist/${slug}/toggle`, { method: 'POST' }).then((res) => {
            if (res.ok && res.data) {
                setWishlisted(res.data.wishlisted);
            }
        });
    };

    const submitReview = () => {
        setSubmittingReview(true);
        setReviewNotice(null);

        const guestFields = isGuestReviewer
            ? { guest_name: guestName, guest_email: guestEmail, order_number: guestOrderNumber }
            : {};

        void apiStore(`/products/${slug}/reviews`, {
            body: { rating: reviewRating, title: reviewTitle || null, body: reviewBody || null, ...guestFields },
        })
            .then((res) => {
                setSubmittingReview(false);
                if (res.ok) {
                    setReviewNotice(t('store.review_submitted'));
                    setShowReviewForm(false);
                    setReviewTitle('');
                    setReviewBody('');
                    setReviewRating(5);
                    setGuestName('');
                    setGuestEmail('');
                    setGuestOrderNumber('');
                } else {
                    setReviewNotice(res.message ?? 'Could not submit review.');
                }
            })
            .catch(() => {
                setSubmittingReview(false);
                setReviewNotice('Could not submit review.');
            });
    };

    if (!product) {
        return (
            <StoreLayout title="Product">
                <div className="store-container py-12">
                    <p className="text-[var(--store-muted)]">Product not found.</p>
                    <Link href="/" className="mt-2 inline-block text-sm text-[var(--store-accent)] hover:underline">
                        {t('store.back_to_home')}
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    return (
        <StoreLayout title={product.name}>
            <Head>
                <title>{product.name}</title>
                {product.short_description && <meta name="description" content={product.short_description} />}
                <meta property="og:title" content={product.name} />
                {product.short_description && <meta property="og:description" content={product.short_description} />}
                {product.primary_image && (
                    <meta
                        property="og:image"
                        content={product.primary_image.startsWith('http') ? product.primary_image : `${window.location.origin}${product.primary_image}`}
                    />
                )}
                <meta property="og:type" content="product" />
                <script
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{
                        __html: JSON.stringify({
                            '@context': 'https://schema.org',
                            '@type': 'Product',
                            name: product.name,
                            description: product.short_description ?? product.description,
                            image: product.primary_image,
                            sku: product.sku,
                            brand: product.brand ? { '@type': 'Brand', name: product.brand.name } : undefined,
                            offers: {
                                '@type': 'Offer',
                                price: unitPrice,
                                priceCurrency: document.documentElement.dataset.currency ?? 'BDT',
                                availability: stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                            },
                            aggregateRating:
                                reviewSummary && reviewSummary.total > 0
                                    ? {
                                          '@type': 'AggregateRating',
                                          ratingValue: reviewSummary.average,
                                          reviewCount: reviewSummary.total,
                                      }
                                    : undefined,
                        }),
                    }}
                />
            </Head>

            <div className="store-container py-6">
                {/* Breadcrumbs */}
                <nav className="mb-6 flex flex-wrap items-center gap-1.5 text-sm text-[var(--store-muted)]">
                    <Link href="/" className="transition hover:text-[var(--store-accent)]">
                        {t('store.home')}
                    </Link>
                    {product.categories.length > 0 && (
                        <>
                            <span className="text-[var(--store-border)]">/</span>
                            <Link href={`/categories/${product.categories[0].slug}`} className="transition hover:text-[var(--store-accent)]">
                                {product.categories[0].name}
                            </Link>
                        </>
                    )}
                    <span className="text-[var(--store-border)]">/</span>
                    <span className="max-w-64 truncate font-medium text-[var(--store-text)]">{product.name}</span>
                </nav>

                <div className="grid gap-10 lg:grid-cols-2">
                    {/* Images */}
                    <div className="flex flex-col gap-3 sm:flex-row">
                        {galleryImages.length > 1 && (
                            <div className="order-last flex snap-x snap-mandatory gap-2.5 overflow-x-auto scroll-p-2 pb-2 [scrollbar-width:none] sm:order-first sm:max-h-[560px] sm:w-24 sm:shrink-0 sm:snap-y sm:flex-col sm:overflow-x-hidden sm:overflow-y-auto sm:pb-0 sm:pe-1 [&::-webkit-scrollbar]:hidden">
                                {galleryImages.map((img) => (
                                    <button
                                        key={img.id}
                                        type="button"
                                        onClick={() => setSelectedImage(img.url)}
                                        className={`h-20 w-20 shrink-0 snap-start overflow-hidden rounded-xl border-2 transition ${
                                            (selectedImage ?? galleryImages[0]?.url) === img.url
                                                ? 'border-[var(--store-accent)]'
                                                : 'border-[var(--store-border)] opacity-70 hover:opacity-100'
                                        }`}
                                    >
                                        <img src={img.url} alt={img.alt_text ?? ''} className="h-full w-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        )}
                        <div className="relative order-first min-w-0 flex-1 sm:order-none">
                            <div className="relative aspect-square overflow-hidden rounded-lg border border-[var(--store-border)] bg-[var(--store-card-hover)]">
                                {(selectedImage ?? galleryImages[0]?.url ?? product.primary_image) ? (
                                    <img src={selectedImage ?? galleryImages[0]?.url ?? product.primary_image ?? ''} alt={product.name} className="h-full w-full object-cover" />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center text-[var(--store-muted)]">No image</div>
                                )}
                            {activeDiscount && (
                                <span className="absolute top-4 start-4 rounded-lg bg-[var(--store-deal)] px-3 py-1 text-xs font-bold text-white shadow">
                                    {activeDiscount.type === 'percentage'
                                        ? `${activeDiscount.value}% ${t('store.sale_badge')}`
                                        : `${formatPrice(activeDiscount.value)} ${t('store.sale_badge')}`}
                                </span>
                            )}
                        </div>
                    </div>
                    </div>

                    {/* Details */}
                    <div className="flex flex-col">
                        {product.brand && (
                            <Link href={`/brands/${product.brand.slug}`} className="text-xs font-bold tracking-[0.18em] text-[var(--store-accent)] uppercase hover:underline">
                                {product.brand.name}
                            </Link>
                        )}

                        <h1 className="store-display mt-2 text-2xl font-bold tracking-tight md:text-3xl">{product.name}</h1>

                        {/* Rating summary */}
                        {reviewSummary && reviewSummary.total > 0 && (
                            <div className="mt-2.5 flex items-center gap-2">
                                <StarRating value={Math.round(reviewSummary.average)} readonly size="sm" />
                                <span className="text-sm text-[var(--store-muted)]">
                                    <span className="font-semibold text-[var(--store-text)]">{reviewSummary.average.toFixed(1)}</span> ({reviewSummary.total}{' '}
                                    {t('store.reviews').toLowerCase()})
                                </span>
                            </div>
                        )}

                        {/* Price */}
                        <div className="mt-4 rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5">
                            <Price value={unitPrice} compareAt={product.compare_at_price} size="lg" />
                            <p className={`mt-2 inline-flex items-center gap-1.5 rounded-lg px-3 py-1 text-xs font-semibold ${stock > 0 ? 'bg-[var(--store-success-soft)] text-[var(--store-success)]' : 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400'}`}>
                                {stock > 0 && <Check className="h-3.5 w-3.5" />}
                                {stock > 0 ? `${stock} ${t('store.in_stock')}` : t('store.out_of_stock')}
                            </p>
                        </div>

                        {product.short_description && <p className="mt-4 leading-relaxed text-[var(--store-muted)]">{product.short_description}</p>}
                        {product.description && <div className="text-sm leading-relaxed text-[var(--store-text)]">{product.description}</div>}

                        {product.categories.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {product.categories.map((cat) => (
                                    <Link
                                        key={cat.id}
                                        href={`/categories/${cat.slug}`}
                                        className="rounded-lg border border-[var(--store-border)] px-3 py-0.5 text-xs text-[var(--store-muted)] hover:border-[var(--store-accent)] hover:text-[var(--store-accent)]"
                                    >
                                        {cat.name}
                                    </Link>
                                ))}
                            </div>
                        )}

                        {attributes.map((attr) => (
                            <div key={attr.id} className="mt-5">
                                <p className="mb-2 text-sm font-semibold">
                                    {attr.name}
                                    {selected[attr.id] !== undefined && (
                                        <span className="ms-2 font-normal text-[var(--store-muted)]">
                                            {attr.values.find((v) => v.id === selected[attr.id])?.value}
                                        </span>
                                    )}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {attr.values.map((val) => {
                                        const isSelected = selected[attr.id] === val.id;
                                        return (
                                            <button
                                                key={val.id}
                                                type="button"
                                                onClick={() => setSelected((prev) => ({ ...prev, [attr.id]: val.id }))}
                                                className={`rounded-lg border px-5 py-2 text-sm font-medium transition ${
                                                    isSelected
                                                        ? 'border-[var(--store-text)] bg-[var(--store-text)] text-[var(--store-bg)]'
                                                        : 'border-[var(--store-border)] bg-[var(--store-card)] hover:border-[var(--store-text)]'
                                                }`}
                                            >
                                                {val.value}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}

                        <div className="mt-6 flex flex-wrap items-center gap-3">
                            <QuantityStepper value={quantity} min={1} max={Math.max(stock, 1)} onChange={setQuantity} />
                            <StoreButton onClick={addToCart} disabled={adding || stock <= 0} className="flex-1">
                                <ShoppingBag className="h-4 w-4" />
                                {adding ? '...' : t('store.add_to_cart')}
                            </StoreButton>
                            <button
                                type="button"
                                onClick={toggleWishlist}
                                aria-label={wishlisted ? t('store.remove_from_wishlist') : t('store.add_to_wishlist')}
                                title={wishlisted ? t('store.remove_from_wishlist') : t('store.add_to_wishlist')}
                                className={`flex h-12 w-12 items-center justify-center rounded-lg border transition ${
                                    wishlisted
                                        ? 'border-red-200 bg-red-50 text-red-600 dark:border-red-900 dark:bg-red-900/20'
                                        : 'border-[var(--store-border)] hover:border-red-300 hover:text-red-500'
                                }`}
                            >
                                <Heart className={`h-5 w-5 ${wishlisted ? 'fill-red-500 text-red-500' : ''}`} />
                            </button>
                        </div>

                        {notice && (
                            <p className={`mt-3 rounded-xl px-4 py-2.5 text-sm font-medium ${notice.includes('Added') ? 'bg-[var(--store-success-soft)] text-[var(--store-success)]' : 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400'}`}>
                                {notice}
                            </p>
                        )}

                        {/* Trust row */}
                        <div className="mt-6 grid grid-cols-3 gap-2 border-t border-[var(--store-border)] pt-5 text-center">
                            {[
                                { icon: Truck, label: t('store.perk_shipping_title') },
                                { icon: ShieldCheck, label: t('store.perk_secure_title') },
                                { icon: RotateCcw, label: t('store.perk_returns_title') },
                            ].map((perk) => (
                                <div key={perk.label} className="flex flex-col items-center gap-1.5">
                                    <perk.icon className="h-5 w-5 text-[var(--store-accent)]" />
                                    <span className="text-[11px] font-semibold text-[var(--store-muted)]">{perk.label}</span>
                                </div>
                            ))}
                        </div>

                        {/* Delivery promise (Daraz-style) */}
                        <div className="mt-4 rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-4 text-sm">
                            <p className="flex items-start gap-2.5">
                                <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-[var(--store-accent)]" />
                                <span>
                                    <span className="font-bold">{t('store.delivery_title')}</span>
                                    <span className="text-[var(--store-muted)]">: {t('store.delivery_window')}</span>
                                </span>
                            </p>
                            <p className="mt-2.5 flex items-start gap-2.5 border-t border-[var(--store-border)] pt-2.5">
                                <Banknote className="mt-0.5 h-4 w-4 shrink-0 text-[var(--store-accent)]" />
                                <span className="font-semibold text-[var(--store-success)]">{t('store.cod_note')}</span>
                            </p>
                        </div>
                    </div>
                </div>

                {/* Reviews Section */}
                <section className="mt-14 border-t border-[var(--store-border)] pt-10">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-xl font-bold tracking-tight md:text-2xl">
                            {t('store.reviews')} ({reviewSummary?.total ?? 0})
                        </h2>
                        <button
                            type="button"
                            onClick={() => setShowReviewForm(!showReviewForm)}
                            className="rounded-lg border border-[var(--store-border)] px-5 py-2 text-sm font-semibold transition hover:border-[var(--store-accent)] hover:text-[var(--store-accent)]"
                        >
                            {t('store.write_review')}
                        </button>
                    </div>

                    {/* Review Form */}
                    {showReviewForm && (
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                submitReview();
                            }}
                            className="mt-6 rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5 md:p-6"
                        >
                            <h3 className="mb-4 font-semibold">{t('store.write_review')}</h3>
                            {isGuestReviewer && (
                                <>
                                    <p className="mb-4 rounded-lg bg-[var(--store-accent-soft)] px-3.5 py-2.5 text-xs leading-relaxed text-[var(--store-accent)]">
                                        {t('store.guest_review_hint')}
                                    </p>
                                    <div className="mb-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium">{t('store.your_name')}</label>
                                            <input
                                                type="text"
                                                required
                                                value={guestName}
                                                onChange={(e) => setGuestName(e.target.value)}
                                                className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition focus:border-[var(--store-accent)]"
                                            />
                                        </div>
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium">{t('store.email')}</label>
                                            <input
                                                type="email"
                                                required
                                                value={guestEmail}
                                                onChange={(e) => setGuestEmail(e.target.value)}
                                                className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition focus:border-[var(--store-accent)]"
                                            />
                                        </div>
                                    </div>
                                    <div className="mb-4">
                                        <label className="mb-1.5 block text-sm font-medium">{t('store.order_number')}</label>
                                        <input
                                            type="text"
                                            required
                                            value={guestOrderNumber}
                                            onChange={(e) => setGuestOrderNumber(e.target.value)}
                                            placeholder="ORD-20240101-ABC123"
                                            className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition focus:border-[var(--store-accent)]"
                                        />
                                    </div>
                                </>
                            )}
                            <div className="mb-4">
                                <label className="mb-1.5 block text-sm font-medium">{t('store.rating')}</label>
                                <StarRating value={reviewRating} onChange={setReviewRating} />
                            </div>
                            <div className="mb-4">
                                <label className="mb-1.5 block text-sm font-medium">Title (optional)</label>
                                <input
                                    type="text"
                                    value={reviewTitle}
                                    onChange={(e) => setReviewTitle(e.target.value)}
                                    className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition focus:border-[var(--store-accent)]"
                                />
                            </div>
                            <div className="mb-4">
                                <label className="mb-1.5 block text-sm font-medium">Review (optional)</label>
                                <textarea
                                    value={reviewBody}
                                    onChange={(e) => setReviewBody(e.target.value)}
                                    rows={3}
                                    className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-input)] px-3.5 py-2.5 text-sm outline-none transition focus:border-[var(--store-accent)]"
                                />
                            </div>
                            <StoreButton
                                type="submit"
                                disabled={submittingReview}
                                size="sm"
                            >
                                {submittingReview ? '...' : t('store.submit_review')}
                            </StoreButton>
                            {reviewNotice && (
                                <p className={`mt-3 text-sm font-medium ${reviewNotice.includes('submitted') ? 'text-[var(--store-success)]' : 'text-red-600 dark:text-red-400'}`}>
                                    {reviewNotice}
                                </p>
                            )}
                        </form>
                    )}

                    {/* Review Summary */}
                    {reviewSummary && reviewSummary.total > 0 && (
                        <div className="mt-6 flex flex-col gap-6 rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5 sm:flex-row sm:items-center md:p-6">
                            <div className="text-center sm:min-w-32">
                                <div className="text-4xl font-bold">{reviewSummary.average.toFixed(1)}</div>
                                <div className="mt-1.5 flex justify-center">
                                    <StarRating value={Math.round(reviewSummary.average)} readonly size="sm" />
                                </div>
                                <div className="mt-1.5 text-xs text-[var(--store-muted)]">
                                    {reviewSummary.total} {t('store.reviews').toLowerCase()}
                                </div>
                            </div>
                            <div className="flex-1 space-y-1.5">
                                {[5, 4, 3, 2, 1].map((star) => (
                                    <div key={star} className="flex items-center gap-2.5 text-sm">
                                        <span className="w-6 text-end font-medium tabular-nums">{star}</span>
                                        <div className="h-2 flex-1 overflow-hidden rounded-lg bg-[var(--store-card-hover)]">
                                            <div
                                                className="h-full rounded-lg bg-[var(--store-star)]"
                                                style={{
                                                    width: `${reviewSummary.total > 0 ? ((reviewSummary.distribution[star] ?? 0) / reviewSummary.total) * 100 : 0}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="w-8 text-[var(--store-muted)] tabular-nums">{reviewSummary.distribution[star] ?? 0}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Review List */}
                    <div className="mt-6 grid gap-4 md:grid-cols-2">
                        {reviews.length === 0 && !showReviewForm ? (
                            <p className="rounded-lg border border-dashed border-[var(--store-border)] p-8 text-center text-[var(--store-muted)] md:col-span-2">
                                {t('store.no_reviews')}
                            </p>
                        ) : (
                            reviews.map((review) => (
                                <div key={review.id} className="rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] p-5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--store-accent-soft)] text-xs font-bold text-[var(--store-accent)]">
                                            {(review.user?.name ?? review.guest_name ?? '?').charAt(0).toUpperCase()}
                                        </span>
                                        <span className="text-sm font-semibold">{review.user?.name ?? review.guest_name ?? 'Guest'}</span>
                                        {review.verified_purchase && (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-[var(--store-success-soft)] px-2 py-0.5 text-[11px] font-bold text-[var(--store-success)]">
                                                <BadgeCheck className="h-3 w-3" />
                                                {t('store.verified_purchase')}
                                            </span>
                                        )}
                                        <span className="text-xs text-[var(--store-muted)]">{new Date(review.created_at).toLocaleDateString()}</span>
                                        <span className="ms-auto">
                                            <StarRating value={review.rating} readonly size="sm" />
                                        </span>
                                    </div>
                                    {review.title && <h4 className="mt-3 font-semibold">{review.title}</h4>}
                                    {review.body && <p className="mt-1 text-sm leading-relaxed text-[var(--store-muted)]">{review.body}</p>}
                                </div>
                            ))
                        )}
                    </div>

                    {/* Review Pagination */}
                    {reviewTotalPages > 1 && (
                        <div className="mt-6 flex justify-center gap-2">
                            {Array.from({ length: reviewTotalPages }, (_, i) => i + 1).map((page) => (
                                <button
                                    key={page}
                                    type="button"
                                    onClick={() => setReviewPage(page)}
                                    className={`rounded-lg px-3.5 py-1.5 text-sm font-medium transition ${
                                        reviewPage === page
                                            ? 'bg-[var(--store-text)] text-[var(--store-bg)]'
                                            : 'border border-[var(--store-border)] hover:border-[var(--store-accent)] hover:text-[var(--store-accent)]'
                                    }`}
                                >
                                    {page}
                                </button>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </StoreLayout>
    );
}
