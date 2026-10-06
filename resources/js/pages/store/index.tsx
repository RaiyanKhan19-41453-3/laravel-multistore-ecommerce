import ProductCard from '@/components/store/product-card';
import ProductImage from '@/components/store/product-image';
import FlashSale from '@/components/store/flash-sale';
import StoreButton from '@/components/store/store-button';
import HomeBanners, { type HomeBanner } from '@/components/store/home-banners';
import SectionHeading from '@/components/store/section-heading';
import StoreLayout from '@/layouts/store-layout';
import { formatPrice } from '@/lib/format';
import { useStore, useT } from '@/lib/store';
import type { ProductSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, BadgePercent, Banknote, ChevronLeft, ChevronRight, RotateCcw, ShieldCheck, Star, Truck, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

interface HomeBlock {
    type: 'section' | 'banner';
    key?: string;
    banner?: HomeBanner;
}

interface HeroSlide {
    id: number;
    eyebrow: string;
    title: string;
    subtitle: string;
    cta_label: string;
    cta_link: string;
    image: string | null;
    layout: string;
    show_eyebrow: boolean;
    show_title: boolean;
    show_subtitle: boolean;
    show_button: boolean;
}

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
    in_stock: boolean;
}

interface Category {
    id: number;
    name: string;
    slug: string;
    image?: string | null;
}

interface Brand {
    id: number;
    name: string;
    slug: string;
    logo: string | null;
}

function toSummary(product: FeaturedProduct): ProductSummary {
    return {
        id: product.id,
        name: product.name,
        slug: product.slug,
        short_description: product.short_description,
        sku: '',
        type: product.variants_count > 0 ? 'variable' : 'simple',
        price: product.price,
        compare_at_price: product.compare_at_price,
        is_featured: true,
        brand: product.brand,
        primary_image: product.primary_image,
        variants_count: product.variants_count,
        in_stock: product.in_stock,
        review_summary: { total: 0, average: 0 },
    };
}

export default function StoreIndex({
    featured,
    categories,
    brands,
    topRated,
    saleItems,
    newArrivals,
    payMethods,
    blocks,
    heroDisplay,
    slides,
}: {
    featured: FeaturedProduct[];
    categories: Category[];
    brands: Brand[];
    topRated: ProductSummary[];
    saleItems: ProductSummary[];
    newArrivals: ProductSummary[];
    payMethods: { value: string; label: string }[];
    blocks: HomeBlock[];
    heroDisplay: string;
    slides: HeroSlide[];
}) {
    const t = useT();
    const store = useStore();
    const [slideIdx, setSlideIdx] = useState(0);
    const [sliderPaused, setSliderPaused] = useState(false);

    const blockIndex = (key: string) => blocks.findIndex((b) => b.type === 'section' && b.key === key);
    const showSection = (key: string) => blockIndex(key) !== -1;

    useEffect(() => {
        if (heroDisplay !== 'slider' || slides.length < 2 || sliderPaused) return;
        if (typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const id = setInterval(() => setSlideIdx((i) => (i + 1) % slides.length), 6000);
        return () => clearInterval(id);
    }, [heroDisplay, slides.length, sliderPaused]);

    const heroImages = featured.filter((p) => p.primary_image).slice(0, 3);
    const spotlight = featured.find((p) => p.compare_at_price && p.compare_at_price > p.price) ?? featured[0] ?? null;
    const spotlightDiscount =
        spotlight?.compare_at_price && spotlight.compare_at_price > spotlight.price
            ? Math.round((1 - spotlight.price / spotlight.compare_at_price) * 100)
            : 0;

    const perks = [
        { icon: Truck, title: t('store.perk_shipping_title'), text: t('store.perk_shipping_text') },
        { icon: RotateCcw, title: t('store.perk_returns_title'), text: t('store.perk_returns_text') },
        { icon: ShieldCheck, title: t('store.perk_secure_title'), text: t('store.perk_secure_text') },
        { icon: Users, title: t('store.perk_support_title'), text: t('store.perk_support_text') },
    ];

    const stats = [
        { value: String(categories.length), label: t('store.stat_categories') },
        { value: String(brands.length), label: t('store.stat_brands') },
        { value: String(featured.length), label: t('store.stat_picks') },
    ];

    return (
        <StoreLayout title="Home">
            <div className="flex flex-col">
            {/* Hero: admin-chosen variant (slider needs saved slides) */}
            {showSection('hero') && heroDisplay === 'slider' && slides.length > 0 ? (
                <section
                    className="relative overflow-hidden"
                    style={{ order: blockIndex('hero') }}
                    onMouseEnter={() => setSliderPaused(true)}
                    onMouseLeave={() => setSliderPaused(false)}
                >
                    {slides.map((slide, i) => (
                        <div
                            key={slide.id}
                            aria-hidden={i !== slideIdx}
                            className={`transition-opacity duration-700 ${i === slideIdx ? 'relative opacity-100' : 'pointer-events-none absolute inset-0 opacity-0'}`}
                        >
                            {slide.layout === 'full' && slide.image ? (
                                <>
                                    <img src={slide.image} alt="" className="absolute inset-0 h-full w-full object-cover" />
                                    <div className="absolute inset-0 bg-gradient-to-r from-[var(--store-bg)] via-[var(--store-bg)]/75 to-transparent" />
                                </>
                            ) : (
                                <div
                                    className="pointer-events-none absolute inset-0"
                                    style={{
                                        backgroundImage:
                                            'radial-gradient(circle at 88% 8%, var(--store-accent-soft) 0, transparent 45%), radial-gradient(circle at 5% 95%, var(--store-accent-soft) 0, transparent 40%)',
                                    }}
                                />
                            )}
                            <span aria-hidden="true" className="store-display pointer-events-none absolute -top-8 end-4 text-[9rem] leading-none font-bold text-[var(--store-text)]/5 select-none md:text-[15rem]">
                                {String(i + 1).padStart(2, '0')}
                            </span>
                            <div className={`store-container relative grid items-center gap-10 py-14 md:py-20 ${slide.layout === 'full' ? '' : 'lg:grid-cols-[1.05fr_0.95fr]'}`}>
                                <div className={slide.layout === 'full' ? 'max-w-2xl' : undefined}>
                                    {slide.show_eyebrow !== false && slide.eyebrow && (
                                        <p className="mb-5 inline-flex items-center gap-2 rounded-lg bg-[var(--store-accent-soft)] px-4 py-1.5 text-[11px] font-bold tracking-[0.22em] text-[var(--store-accent)] uppercase">
                                            <BadgePercent className="h-4 w-4" />
                                            {slide.eyebrow}
                                        </p>
                                    )}
                                    {slide.show_title !== false && (
                                        <h1 className="store-display text-5xl leading-[1.02] font-bold tracking-tight text-balance md:text-7xl">
                                            {slide.title}
                                        </h1>
                                    )}
                                    {slide.show_subtitle !== false && slide.subtitle && (
                                        <p className="mt-4 max-w-xl text-base leading-relaxed text-[var(--store-muted)] md:text-lg">{slide.subtitle}</p>
                                    )}
                                    {slide.show_button !== false && slide.cta_label && (
                                        <div className="mt-8 flex flex-wrap items-center gap-4">
                                            <StoreButton href={slide.cta_link} size="lg">
                                                {slide.cta_label}
                                                <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                                            </StoreButton>
                                            <span className="inline-flex items-center gap-1.5 rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] px-3.5 py-2 text-xs font-semibold text-[var(--store-muted)]">
                                                <Banknote className="h-4 w-4 text-[var(--store-accent)]" />
                                                {t('store.cod_note')}
                                            </span>
                                        </div>
                                    )}
                                    <div className="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-xs font-medium text-[var(--store-muted)]">
                                        <span className="inline-flex items-center gap-1.5">
                                            <Truck className="h-4 w-4 text-[var(--store-star)]" />
                                            {t('store.perk_shipping_title')}
                                        </span>
                                        <span className="inline-flex items-center gap-1.5">
                                            <ShieldCheck className="h-4 w-4 text-[var(--store-star)]" />
                                            {t('store.perk_secure_title')}
                                        </span>
                                        <span className="inline-flex items-center gap-1.5">
                                            <RotateCcw className="h-4 w-4 text-[var(--store-star)]" />
                                            {t('store.perk_returns_title')}
                                        </span>
                                    </div>
                                </div>
                                {slide.layout !== 'full' && (
                                <div className="relative hidden sm:block">
                                    <div aria-hidden="true" className="absolute inset-4 rotate-3 rounded-t-[999px] rounded-b-[2rem] bg-[var(--store-accent-soft)]" />
                                    <div className="relative mx-auto aspect-[4/5] max-h-[540px] w-full max-w-md overflow-hidden rounded-t-[999px] rounded-b-[2rem] border border-[var(--store-border)] shadow-2xl">
                                        {slide.image ? (
                                            <img src={slide.image} alt="" className="absolute inset-0 h-full w-full object-cover" />
                                        ) : (
                                            <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-[var(--store-accent)] to-[var(--store-accent-strong)]">
                                                <BadgePercent className="h-20 w-20 text-white/70" />
                                            </div>
                                        )}
                                    </div>
                                    <span className="absolute top-8 -start-2 flex items-center gap-1.5 rounded-lg bg-white px-3.5 py-2 text-xs font-bold text-neutral-900 shadow-xl">
                                        <Star className="h-4 w-4 fill-[var(--store-star)] text-[var(--store-star)]" />
                                        {t('store.rating_sort')}
                                    </span>
                                    <span className="absolute -bottom-4 end-6 rounded-lg bg-[var(--store-accent)] px-4 py-2.5 text-xs font-bold text-[var(--store-accent-ink)] shadow-xl">
                                        {t('store.shop_now')}
                                    </span>
                                </div>
                                )}
                            </div>
                        </div>
                    ))}
                    <div className="store-container absolute inset-x-0 bottom-0 flex items-center gap-3 pb-6">
                        <div className="flex gap-1.5">
                            {slides.map((slide, i) => (
                                <button
                                    key={slide.id}
                                    type="button"
                                    aria-label={`Go to slide ${i + 1}`}
                                    onClick={() => setSlideIdx(i)}
                                    className={`h-2 rounded-lg transition-all ${i === slideIdx ? 'w-7 bg-[var(--store-accent)]' : 'w-2 bg-[var(--store-text)]/20 hover:bg-[var(--store-text)]/40'}`}
                                />
                            ))}
                        </div>
                        <span className="text-xs font-bold tabular-nums text-[var(--store-muted)]">
                            {String(slideIdx + 1).padStart(2, '0')} / {String(slides.length).padStart(2, '0')}
                        </span>
                        <span className="flex gap-2">
                            <button
                                type="button"
                                aria-label="Previous slide"
                                onClick={() => setSlideIdx((slideIdx - 1 + slides.length) % slides.length)}
                                className="flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] transition hover:border-[var(--store-accent)] hover:text-[var(--store-accent)]"
                            >
                                <ChevronLeft className="h-4 w-4 rtl:rotate-180" />
                            </button>
                            <button
                                type="button"
                                aria-label="Next slide"
                                onClick={() => setSlideIdx((slideIdx + 1) % slides.length)}
                                className="flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] transition hover:border-[var(--store-accent)] hover:text-[var(--store-accent)]"
                            >
                                <ChevronRight className="h-4 w-4 rtl:rotate-180" />
                            </button>
                        </span>
                    </div>
                </section>
            ) : showSection('hero') && heroDisplay === 'centered' ? (
                <section className="relative overflow-hidden bg-[var(--store-text)] text-[var(--store-bg)]" style={{ order: blockIndex('hero') }}>
                    <div
                        className="pointer-events-none absolute inset-0 opacity-20"
                        style={{
                            backgroundImage:
                                'radial-gradient(circle at 50% 0%, var(--store-accent) 0, transparent 50%), radial-gradient(circle at 50% 110%, var(--store-accent) 0, transparent 40%)',
                        }}
                    />
                    <div className="store-container relative mx-auto max-w-3xl py-20 text-center md:py-28">
                        <p className="mb-5 inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/5 px-4 py-1.5 text-[11px] font-bold tracking-[0.22em] uppercase backdrop-blur">
                            <BadgePercent className="h-4 w-4 text-[var(--store-star)]" />
                            {t('store.hero_eyebrow')}
                        </p>
                        <h1 className="store-display text-4xl leading-[1.05] font-bold tracking-tight text-balance md:text-6xl">{t('store.hero_title')}</h1>
                        <p className="mx-auto mt-5 max-w-xl text-base leading-relaxed opacity-70 md:text-lg">{t('store.hero_subtitle')}</p>
                        <div className="mt-8 flex flex-wrap justify-center gap-3">
                            <StoreButton href="/products" size="lg">
                                {t('store.shop_now')}
                                <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                            </StoreButton>
                            {categories.length > 0 && (
                                <a
                                    href="#categories"
                                    className="inline-flex items-center gap-2 rounded-full border border-white/30 px-7 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10"
                                >
                                    {t('store.browse_categories')}
                                </a>
                            )}
                        </div>
                        <dl className="mt-10 flex justify-center gap-8">
                            {stats.map((stat) => (
                                <div key={stat.label}>
                                    <dt className="sr-only">{stat.label}</dt>
                                    <dd className="text-2xl font-black tabular-nums md:text-3xl">{stat.value}</dd>
                                    <dd className="mt-0.5 text-xs font-medium tracking-wide opacity-60 uppercase">{stat.label}</dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                </section>
            ) : (
                showSection('hero') && (
            <section className="relative overflow-hidden bg-[var(--store-text)] text-[var(--store-bg)]" style={{ order: blockIndex('hero') }}>
                <div
                    className="pointer-events-none absolute inset-0 opacity-20"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 85% 15%, var(--store-accent) 0, transparent 45%), radial-gradient(circle at 10% 90%, var(--store-accent) 0, transparent 35%)',
                    }}
                />
                <div className="store-container relative grid items-center gap-12 py-16 md:py-24 lg:grid-cols-[1.1fr_1fr]">
                    <div>
                        <p className="mb-5 inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/5 px-4 py-1.5 text-[11px] font-bold tracking-[0.22em] uppercase">
                            <BadgePercent className="h-4 w-4 text-[var(--store-star)]" />
                            {t('store.hero_eyebrow')}
                        </p>
                        <h1 className="store-display text-4xl leading-[1.05] font-bold tracking-tight text-balance md:text-6xl">{t('store.hero_title')}</h1>
                        <p className="mt-5 max-w-xl text-base leading-relaxed opacity-70 md:text-lg">{t('store.hero_subtitle')}</p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <StoreButton href="/products" size="lg">
                                {t('store.shop_now')}
                                <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                            </StoreButton>
                            {categories.length > 0 && (
                                <a
                                    href="#categories"
                                    className="inline-flex items-center gap-2 rounded-full border border-white/25 px-7 py-3.5 text-sm font-semibold transition hover:bg-white/10"
                                >
                                    {t('store.browse_categories')}
                                </a>
                            )}
                        </div>
                        <dl className="mt-10 flex gap-8">
                            {stats.map((stat) => (
                                <div key={stat.label}>
                                    <dt className="sr-only">{stat.label}</dt>
                                    <dd className="text-2xl font-black tabular-nums md:text-3xl">{stat.value}</dd>
                                    <dd className="mt-0.5 text-xs font-medium tracking-wide opacity-60 uppercase">{stat.label}</dd>
                                </div>
                            ))}
                        </dl>
                    </div>

                    {/* Live collage */}
                    <div className="relative hidden min-h-105 select-none sm:block" aria-hidden={heroImages.length === 0}>
                        {heroImages.length > 0 ? (
                            <>
                                {heroImages[0] && (
                                    <Link
                                        href={`/products/${heroImages[0].slug}`}
                                        className="absolute top-0 left-0 w-3/5 rotate-[-4deg] overflow-hidden rounded-xl border border-white/15 shadow-2xl transition duration-300 hover:rotate-0"
                                    >
                                        <img src={heroImages[0].primary_image ?? ''} alt="" className="aspect-[3/4] w-full object-cover" />
                                        <span className="absolute bottom-3 left-3 rounded-lg bg-black/60 px-3 py-1 text-xs font-bold text-white backdrop-blur">
                                            {formatPrice(heroImages[0].price)}
                                        </span>
                                    </Link>
                                )}
                                {heroImages[1] && (
                                    <Link
                                        href={`/products/${heroImages[1].slug}`}
                                        className="absolute top-10 right-0 w-1/2 rotate-[5deg] overflow-hidden rounded-xl border border-white/15 shadow-2xl transition duration-300 hover:rotate-0"
                                    >
                                        <img src={heroImages[1].primary_image ?? ''} alt="" className="aspect-square w-full object-cover" />
                                    </Link>
                                )}
                                {heroImages[2] && (
                                    <Link
                                        href={`/products/${heroImages[2].slug}`}
                                        className="absolute bottom-0 left-1/4 w-2/5 rotate-[2deg] overflow-hidden rounded-xl border border-white/15 shadow-2xl transition duration-300 hover:rotate-0"
                                    >
                                        <img src={heroImages[2].primary_image ?? ''} alt="" className="aspect-[4/3] w-full object-cover" />
                                        <span className="absolute top-3 right-3 flex items-center gap-1 rounded-lg bg-black/60 px-2.5 py-1 text-[11px] font-bold text-white backdrop-blur">
                                            <Star className="h-3 w-3 fill-[var(--store-star)] text-[var(--store-star)]" />
                                            {t('store.rating_sort')}
                                        </span>
                                    </Link>
                                )}
                            </>
                        ) : (
                            <div className="flex h-full min-h-80 items-center justify-center rounded-xl border border-dashed border-white/20 text-sm opacity-50">
                                {store.name}
                            </div>
                        )}
                    </div>
                </div>
            </section>
            )
            )}

            {/* Category quick chips */}
            {categories.length > 0 && (
                <div className="border-b border-[var(--store-border)] bg-[var(--store-card)]">
                    <div className="store-container flex gap-2 overflow-x-auto py-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <Link
                            href="/products"
                            className="shrink-0 rounded-lg border border-[var(--store-border)] px-4 py-1.5 text-sm font-semibold whitespace-nowrap transition hover:border-[var(--store-text)]"
                        >
                            {t('store.all_categories')}
                        </Link>
                        {categories.map((cat) => (
                            <Link
                                key={cat.id}
                                href={`/categories/${cat.slug}`}
                                className="shrink-0 rounded-lg border border-[var(--store-border)] px-4 py-1.5 text-sm font-semibold whitespace-nowrap transition hover:border-[var(--store-text)]"
                            >
                                {cat.name}
                            </Link>
                        ))}
                    </div>
                </div>
            )}

            {/* Brand marquee */}
            {showSection('brands') && brands.length > 0 && (
                <section aria-label={t('store.brands_eyebrow')} className="store-marquee overflow-hidden border-b border-[var(--store-border)] bg-[var(--store-card)] py-5" style={{ order: blockIndex('brands') }}>
                    <div className="store-marquee-track flex w-max items-center gap-10 pe-10">
                        {[...brands, ...brands].map((brand, i) => (
                            <Link
                                key={`${brand.id}-${i}`}
                                href={`/brands/${brand.slug}`}
                                aria-hidden={i >= brands.length}
                                tabIndex={i >= brands.length ? -1 : undefined}
                                className="flex shrink-0 items-center gap-2.5 opacity-60 transition hover:opacity-100"
                            >
                                {brand.logo ? (
                                    <img src={brand.logo} alt={brand.name} className="h-7 w-auto object-contain" loading="lazy" />
                                ) : (
                                    <span className="text-lg font-black tracking-tight whitespace-nowrap">{brand.name}</span>
                                )}
                            </Link>
                        ))}
                    </div>
                </section>
            )}

            {/* Category showcase */}
            {showSection('categories') && categories.length > 0 && (
                <section id="categories" className="scroll-mt-24 py-16" style={{ order: blockIndex('categories') }}>
                    <div className="store-container">
                        <SectionHeading eyebrow={t('store.categories')} title={t('store.shop_by_category')} subtitle={t('store.category_subtitle')} />
                    </div>
                    <div className="store-container">
                        <div className="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            {categories.map((category) => (
                                <Link
                                    key={category.id}
                                    href={`/categories/${category.slug}`}
                                    className="group relative aspect-[3/4] w-[68%] shrink-0 snap-start overflow-hidden rounded-xl transition duration-300 hover:-translate-y-1 hover:shadow-[var(--store-shadow)] sm:w-[36%] lg:w-[22.5%]"
                                >
                                    <div className="absolute inset-0 transition duration-500 group-hover:scale-105">
                                        <ProductImage src={category.image ?? null} seed={`cat-${category.slug}`} alt="" />
                                    </div>
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent" />
                                    <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 p-5">
                                        <div>
                                            <h3 className="store-display text-xl font-bold text-white md:text-2xl">{category.name}</h3>
                                            <span className="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-white/80 opacity-0 transition duration-300 group-hover:opacity-100">
                                                {t('store.shop_now')}
                                                <ArrowRight className="h-3.5 w-3.5 rtl:rotate-180" />
                                            </span>
                                        </div>
                                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/15 text-white backdrop-blur transition group-hover:bg-[var(--store-accent)]">
                                            <ArrowUpRight className="h-4 w-4" />
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* Featured Products */}
            {showSection('featured') && (
            <section id="featured" className="scroll-mt-24 bg-[var(--store-card)] py-12" style={{ order: blockIndex('featured') }}>
                <div className="store-container">
                    <SectionHeading
                        eyebrow={t('store.products')}
                        title={t('store.featured_products')}
                        subtitle={t('store.featured_subtitle')}
                        actionHref="/products"
                        actionLabel={t('store.view_all')}
                    />

                    {featured.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-[var(--store-border)] p-10 text-center text-[var(--store-muted)]">
                            {t('store.no_products')}
                        </p>
                    ) : (
                        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5 xl:grid-cols-6">
                            {featured.map((product) => (
                                <ProductCard key={product.id} product={toSummary(product)} />
                            ))}
                        </div>
                    )}
                </div>
            </section>
            )}

            {/* Flash sale countdown rail */}
            {showSection('sale') && saleItems.length > 0 && (
                <div style={{ order: blockIndex('sale') }}>
                    <FlashSale
                        items={saleItems}
                        title={t('store.flash_sale')}
                        endsLabel={t('store.ends_in')}
                        shopAllLabel={t('store.view_all')}
                        hoursLabel={t('store.hours_short')}
                        minutesLabel={t('store.minutes_short')}
                        secondsLabel={t('store.seconds_short')}
                    />
                </div>
            )}

            {/* On sale carousel */}
            {showSection('sale') && saleItems.length > 0 && (
            <section className="scroll-mt-24 bg-[var(--store-card)] py-12" style={{ order: blockIndex('sale') }}>
                <div className="store-container">
                    <SectionHeading
                        eyebrow={t('store.sale_badge')}
                        title={t('store.on_sale')}
                        subtitle={t('store.on_sale_subtitle')}
                        actionHref="/products"
                        actionLabel={t('store.view_all')}
                    />
                </div>
                <div className="store-container">
                    <div className="flex snap-x gap-3 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        {saleItems.map((product) => (
                            <div key={product.id} className="w-[150px] shrink-0 snap-start sm:w-[180px]">
                                <ProductCard product={product} compact />
                            </div>
                        ))}
                    </div>
                </div>
            </section>
            )}

            {/* New arrivals grid */}
            {showSection('new_arrivals') && newArrivals.length > 0 && (
            <section className="store-container py-12" style={{ order: blockIndex('new_arrivals') }}>
                <SectionHeading
                    title={t('store.new_arrivals')}
                    subtitle={t('store.new_arrivals_subtitle')}
                    actionHref="/products?sort=created_at&direction=desc"
                    actionLabel={t('store.view_all')}
                />
                <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5 xl:grid-cols-6">
                    {newArrivals.map((product) => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                </div>
            </section>
            )}

            {/* Top rated rank list */}
            {showSection('top_rated') && topRated.length > 0 && (
                <section className="store-container py-12" style={{ order: blockIndex('top_rated') }}>
                    <SectionHeading title={t('store.rating_sort')} subtitle={t('store.top_rated_subtitle')} actionHref="/products?sort=rating&direction=desc" actionLabel={t('store.view_all')} />
                    <ol className="grid gap-4 md:grid-cols-2">
                        {topRated.map((product, i) => (
                            <li key={product.id}>
                                <Link
                                    href={`/products/${product.slug}`}
                                    className="group flex items-center gap-5 rounded-xl border border-[var(--store-border)] bg-[var(--store-card)] p-4 transition hover:-translate-y-0.5 hover:shadow-[var(--store-shadow)]"
                                >
                                    <span className="store-display text-4xl font-bold tabular-nums text-[var(--store-border)] transition group-hover:text-[var(--store-accent)] md:text-5xl">
                                        {i + 1}
                                    </span>
                                    <span className="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-[var(--store-card-hover)]">
                                        <ProductImage src={product.primary_image} seed={product.id} alt="" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-bold">{product.name}</span>
                                        <span className="mt-1 flex items-center gap-1.5 text-xs text-[var(--store-muted)]">
                                            <Star className="h-3.5 w-3.5 fill-[var(--store-star)] text-[var(--store-star)]" />
                                            <span className="font-bold text-[var(--store-text)]">{product.review_summary.average.toFixed(1)}</span>(
                                            {product.review_summary.total})
                                        </span>
                                        <span className="mt-1 block text-sm font-bold">{formatPrice(product.price)}</span>
                                    </span>
                                    <ArrowUpRight className="h-5 w-5 shrink-0 text-[var(--store-muted)] transition group-hover:text-[var(--store-accent)]" />
                                </Link>
                            </li>
                        ))}
                    </ol>
                </section>
            )}

            {/* Spotlight deal */}
            {showSection('spotlight') && spotlight && (
                <section style={{ order: blockIndex('spotlight') }}>
                    <div className="grid bg-[var(--store-text)] text-[var(--store-bg)] md:grid-cols-2">
                        <div className="relative min-h-72 md:min-h-105">
                            <ProductImage src={spotlight.primary_image} seed={spotlight.id} alt={spotlight.name} eager className="absolute inset-0 h-full w-full object-cover" />
                            {spotlightDiscount > 0 && (
                                <span className="absolute top-5 start-5 rounded-lg bg-[var(--store-deal)] px-3.5 py-1.5 text-sm font-black text-white shadow-xl">
                                    -{spotlightDiscount}%
                                </span>
                            )}
                        </div>
                        <div className="flex flex-col justify-center p-8 md:p-12">
                            <p className="text-[11px] font-bold tracking-[0.22em] text-[var(--store-star)] uppercase">{t('store.spotlight_eyebrow')}</p>
                            {spotlight.brand && <p className="mt-3 text-xs font-bold tracking-widest uppercase opacity-60">{spotlight.brand.name}</p>}
                            <h2 className="store-display mt-1 text-2xl font-bold tracking-tight text-balance md:text-4xl">{spotlight.name}</h2>
                            {spotlight.short_description && <p className="mt-3 max-w-md text-sm leading-relaxed opacity-70">{spotlight.short_description}</p>}
                            <div className="mt-5 flex items-baseline gap-3">
                                <span className="text-3xl font-black">{formatPrice(spotlight.price)}</span>
                                {spotlight.compare_at_price && spotlight.compare_at_price > spotlight.price && (
                                    <span className="text-lg opacity-50 line-through">{formatPrice(spotlight.compare_at_price)}</span>
                                )}
                            </div>
                            <div className="mt-7">
                                <StoreButton href={`/products/${spotlight.slug}`} size="lg">
                                    {t('store.shop_now')}
                                    <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                                </StoreButton>
                            </div>
                        </div>
                    </div>
                </section>
            )}

            {/* Custom banners, each exactly where the merchant placed it */}
            {blocks.map((block, i) =>
                block.type === 'banner' && block.banner ? (
                    <div key={`banner-${block.banner.id}`} style={{ order: i }} className="store-container py-16">
                        <HomeBanners banners={[block.banner]} />
                    </div>
                ) : null,
            )}

            {/* Perks */}
            {showSection('perks') && (
            <section className="border-t border-[var(--store-border)] bg-[var(--store-card)]" style={{ order: blockIndex('perks') }}>
                <div className="store-container grid grid-cols-2 gap-6 py-10 lg:grid-cols-4">
                    {perks.map((perk) => (
                        <div key={perk.title} className="flex items-start gap-3">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[var(--store-accent-soft)] text-[var(--store-accent)]">
                                <perk.icon className="h-5 w-5" />
                            </span>
                            <div>
                                <h3 className="text-sm font-bold">{perk.title}</h3>
                                <p className="mt-0.5 text-xs leading-relaxed text-[var(--store-muted)]">{perk.text}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </section>
            )}

            {/* Accepted payments */}
            {payMethods.length > 0 && (
                <div className="border-t border-[var(--store-border)]">
                    <div className="store-container flex flex-wrap items-center gap-x-5 gap-y-2 py-4">
                        <span className="text-xs font-bold tracking-wider text-[var(--store-muted)] uppercase">{t('store.we_accept')}</span>
                        {payMethods.map((m) => (
                            <span key={m.value} className="inline-flex items-center gap-1.5 rounded-lg border border-[var(--store-border)] bg-[var(--store-card)] px-3 py-1.5 text-xs font-semibold">
                                <Banknote className="h-3.5 w-3.5 text-[var(--store-accent)]" />
                                {m.label}
                            </span>
                        ))}
                    </div>
                </div>
            )}
            </div>
        </StoreLayout>
    );
}
