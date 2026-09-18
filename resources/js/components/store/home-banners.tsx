import { ArrowRight } from 'lucide-react';
import { SmartLink } from './site-nav';

export interface HomeBanner {
    id: number;
    title: string;
    subtitle: string;
    button_label: string;
    button_link: string;
    layout: 'single' | 'double' | 'quad';
    text_layout: 'split' | 'left' | 'center';
    show_title: boolean;
    show_subtitle: boolean;
    show_button: boolean;
    media_type: 'image' | 'iframe';
    iframe_url: string | null;
    images: string[];
}

function aspectFor(layout: HomeBanner['layout']): string {
    if (layout === 'single') return 'aspect-[21/9]';
    if (layout === 'double') return 'aspect-video';
    return 'aspect-square';
}

function BannerMedia({ banner }: { banner: HomeBanner }) {
    if (banner.media_type === 'iframe') {
        if (!banner.iframe_url) return null;

        return (
            <div className="overflow-hidden rounded-xl bg-black">
                <iframe
                    src={banner.iframe_url}
                    title={banner.title || 'Embedded content'}
                    loading="lazy"
                    allowFullScreen
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    className={`w-full ${aspectFor(banner.layout)}`}
                />
            </div>
        );
    }

    const images = banner.images.slice(0, banner.layout === 'single' ? 1 : banner.layout === 'double' ? 2 : 4);

    if (images.length === 0) return null;

    if (banner.layout === 'single') {
        return (
            <div className="overflow-hidden rounded-xl">
                <img src={images[0]} alt="" loading="lazy" className="aspect-[21/9] w-full object-cover" />
            </div>
        );
    }

    if (banner.layout === 'double') {
        return (
            <div className="grid gap-4 sm:grid-cols-2">
                {images.map((src, i) => (
                    <div key={i} className="overflow-hidden rounded-xl">
                        <img src={src} alt="" loading="lazy" className="aspect-[4/3] w-full object-cover" />
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            {images.map((src, i) => (
                <div key={i} className="overflow-hidden rounded-xl">
                    <img src={src} alt="" loading="lazy" className="aspect-square w-full object-cover" />
                </div>
            ))}
        </div>
    );
}

function BannerButton({ banner, className = '' }: { banner: HomeBanner; className?: string }) {
    if (!banner.show_button || !banner.button_label) return null;

    return (
        <SmartLink
            href={banner.button_link}
            className={`inline-flex items-center gap-2 rounded-lg bg-[var(--store-accent)] px-7 py-3 text-sm font-bold text-[var(--store-accent-ink)] transition hover:-translate-y-0.5 hover:opacity-90 ${className}`}
        >
            {banner.button_label}
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
        </SmartLink>
    );
}

/**
 * Merchant-composed banner blocks: title, subtitle, button, and
 * images or one embed, with controllable placement and visibility.
 */
export default function HomeBanners({ banners }: { banners: HomeBanner[] }) {
    const visible = banners.filter(
        (b) =>
            (b.show_title && b.title) ||
            (b.show_subtitle && b.subtitle) ||
            (b.show_button && b.button_label) ||
            (b.media_type === 'iframe' ? !!b.iframe_url : b.images.length > 0),
    );

    if (visible.length === 0) return null;

    return (
        <>
            {visible.map((banner) => (
                <section key={banner.id} aria-label={banner.title || 'Banner'}>
                    {banner.text_layout === 'center' ? (
                        <div className="text-center">
                            {banner.show_title && banner.title && <h2 className="store-display text-2xl font-bold tracking-tight text-balance md:text-4xl">{banner.title}</h2>}
                            {banner.show_subtitle && banner.subtitle && (
                                <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-[var(--store-muted)] md:text-base">{banner.subtitle}</p>
                            )}
                            <div className="mt-7">
                                <BannerMedia banner={banner} />
                            </div>
                            {banner.show_button && banner.button_label && (
                                <div className="mt-7">
                                    <BannerButton banner={banner} />
                                </div>
                            )}
                        </div>
                    ) : (
                        <>
                            <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                                <div className="max-w-2xl">
                                    {banner.show_title && banner.title && (
                                        <h2 className="store-display text-2xl font-bold tracking-tight text-balance md:text-4xl">{banner.title}</h2>
                                    )}
                                    {banner.show_subtitle && banner.subtitle && (
                                        <p className="mt-2 text-sm leading-relaxed text-[var(--store-muted)] md:text-base">{banner.subtitle}</p>
                                    )}
                                </div>
                                {banner.text_layout === 'split' && <BannerButton banner={banner} className="shrink-0" />}
                            </div>
                            <BannerMedia banner={banner} />
                        </>
                    )}
                </section>
            ))}
        </>
    );
}
