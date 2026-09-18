<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\HeroSlide;
use App\Models\HomepageSection;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\DB;

class HomepageService
{
    public const HERO_DISPLAYS = ['split', 'slider', 'centered'];

    public function __construct(
        protected SettingsService $settings = new SettingsService,
    ) {}

    /**
     * Ordered homepage blocks for the current store: section blocks
     * and individual banner blocks interleaved in merchant order.
     * Falls back to the canonical defaults when nothing is saved yet
     * (no write on read). An explicitly emptied layout stays empty.
     *
     * @return array<int, array{type: string, key?: string, banner?: array<string, mixed>}>
     */
    public function blocks(): array
    {
        /** @var array<int, array{type: string, key?: string, banner?: array<string, mixed>}> $blocks */
        $blocks = CatalogCache::rememberBlocks(function () {
            $rows = HomepageSection::with('banner')->ordered()->get();

            if ($rows->isEmpty()) {
                return $this->defaultBlocks();
            }

            $blocks = [];

            foreach ($rows as $row) {
                if ($row->banner_id !== null) {
                    $banner = $row->is_active && $row->banner && $row->banner->is_active
                        ? $this->bannerNode($row->banner)
                        : null;

                    if ($banner !== null) {
                        $blocks[] = ['type' => 'banner', 'banner' => $banner];
                    }

                    continue;
                }

                if (! in_array($row->key, HomepageSection::KEYS, true)) {
                    continue;
                }

                if ($row->key === 'banners') {
                    // Legacy group slot: expand to every active banner.
                    foreach ($this->activeBanners() as $banner) {
                        $blocks[] = ['type' => 'banner', 'banner' => $banner];
                    }

                    continue;
                }

                if ($row->is_active) {
                    $blocks[] = ['type' => 'section', 'key' => $row->key];
                }
            }

            return $blocks;
        });

        return $blocks;
    }

    /**
     * Ordered keys of the active homepage sections for the current
     * store. Falls back to the canonical defaults only when the
     * merchant has not saved a layout yet (no write on read). An
     * explicitly emptied layout stays empty.
     *
     * @return array<int, string>
     */
    public function sections(): array
    {
        /** @var array<int, string> $keys */
        $keys = CatalogCache::rememberHomepage(function () {
            $rows = HomepageSection::ordered()->get(['key', 'is_active']);

            if ($rows->isEmpty()) {
                return HomepageSection::KEYS;
            }

            return $rows->where('is_active', true)
                ->pluck('key')
                ->filter(fn ($key) => in_array($key, HomepageSection::KEYS, true))
                ->values()
                ->all();
        });

        return $keys;
    }

    /**
     * Canonical default layout: every section in order, banners
     * expanded after the spotlight slot.
     *
     * @return array<int, array{type: string, key?: string, banner?: array<string, mixed>}>
     */
    private function defaultBlocks(): array
    {
        $blocks = [];

        foreach (HomepageSection::KEYS as $key) {
            if ($key === 'banners') {
                foreach ($this->activeBanners() as $banner) {
                    $blocks[] = ['type' => 'banner', 'banner' => $banner];
                }

                continue;
            }

            $blocks[] = ['type' => 'section', 'key' => $key];
        }

        return $blocks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeBanners(): array
    {
        return Banner::active()->ordered()->get()->map(fn (Banner $banner) => $this->bannerNode($banner))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function bannerNode(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->displayTitle(),
            'subtitle' => $banner->displaySubtitle(),
            'button_label' => $banner->displayButtonLabel(),
            'button_link' => MenuService::safeUrl($banner->button_link) ?? '/products',
            'layout' => in_array($banner->layout, Banner::LAYOUTS, true) ? $banner->layout : 'single',
            'text_layout' => in_array($banner->text_layout, Banner::TEXT_LAYOUTS, true) ? $banner->text_layout : 'split',
            'show_title' => (bool) $banner->show_title,
            'show_subtitle' => (bool) $banner->show_subtitle,
            'show_button' => (bool) $banner->show_button,
            'media_type' => in_array($banner->media_type, Banner::MEDIA_TYPES, true) ? $banner->media_type : 'image',
            'iframe_url' => self::safeEmbedUrl($banner->iframe_url),
            'images' => $banner->imageList(),
        ];
    }

    /**
     * Embeds must be absolute https URLs — no scripts, no data URIs.
     */
    public static function safeEmbedUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || ! preg_match('#^https://#i', $url)) {
            return null;
        }

        return $url;
    }

    /**
     * Full layout for the admin manager: saved rows overlaid on the
     * canonical key order so new section types appear automatically.
     * Banner rows resolve to their banner for inline display.
     *
     * @return array<int, array{key: string|null, banner_id: int|null, title: string|null, is_active: bool, sort_order: int}>
     */
    public function layoutForAdmin(): array
    {
        $saved = HomepageSection::with('banner')->ordered()->get();

        if ($saved->isEmpty()) {
            $layout = [];
            $i = 0;

            foreach (HomepageSection::KEYS as $key) {
                if ($key === 'banners') {
                    foreach (Banner::ordered()->get() as $banner) {
                        $layout[] = [
                            'key' => null,
                            'banner_id' => $banner->id,
                            'title' => $banner->title,
                            'is_active' => (bool) $banner->is_active,
                            'sort_order' => $i++,
                        ];
                    }

                    continue;
                }

                $layout[] = ['key' => $key, 'banner_id' => null, 'title' => null, 'is_active' => true, 'sort_order' => $i++];
            }

            return $layout;
        }

        $rows = $saved->map(fn (HomepageSection $row) => [
            'key' => $row->key,
            'banner_id' => $row->banner_id,
            'title' => $row->banner?->title,
            'is_active' => (bool) $row->is_active,
            'sort_order' => (int) $row->sort_order,
        ])->all();

        // Banners created after the layout was saved still need a slot:
        // slot them right after the spotlight section (or at the end).
        $placed = collect($rows)->pluck('banner_id')->filter()->all();
        $missing = Banner::ordered()->whereNotIn('id', $placed)->get();

        if ($missing->isEmpty()) {
            return $rows;
        }

        $extra = $missing->map(fn (Banner $banner) => [
            'key' => null,
            'banner_id' => $banner->id,
            'title' => $banner->title,
            'is_active' => (bool) $banner->is_active,
            'sort_order' => 0,
        ])->all();

        $spotlightAt = collect($rows)->search(fn ($row) => $row['key'] === 'spotlight' && $row['banner_id'] === null);

        if ($spotlightAt === false) {
            return array_merge($rows, $extra);
        }

        return array_merge(
            array_slice($rows, 0, $spotlightAt + 1),
            $extra,
            array_slice($rows, $spotlightAt + 1)
        );
    }

    /**
     * Persist the whole block layout in one request, replacing the
     * previous rows. Shape is validated by the controller: every
     * block is a known section or a live banner of this store.
     *
     * @param  array<int, array{type: string, key?: string|null, banner_id?: int|null, is_active: bool, sort_order: int}>  $blocks
     */
    public function saveBlocks(array $blocks): void
    {
        $storeId = app(CurrentStore::class)->scopeId();

        DB::transaction(function () use ($blocks, $storeId) {
            HomepageSection::withoutGlobalScope(BelongsToStore::class)
                ->when(
                    $storeId !== null,
                    fn ($q) => $q->where('store_id', $storeId),
                    fn ($q) => $q->whereNull('store_id')
                )
                ->delete();

            foreach ($blocks as $position => $block) {
                HomepageSection::create([
                    'store_id' => $storeId,
                    'key' => $block['type'] === 'section' ? $block['key'] : 'banners',
                    'banner_id' => $block['type'] === 'banner' ? $block['banner_id'] : null,
                    'is_active' => $block['is_active'],
                    'sort_order' => $block['sort_order'] ?? $position,
                ]);
            }
        });

        $this->flush();
    }

    /**
     * Active banners, localized and ordered, with safe links.
     * Empty text + hidden button variants are preserved as-is;
     * the frontend skips fully-empty banners.
     *
     * @return array<int, array<string, mixed>>
     */
    public function banners(): array
    {
        /** @var array<int, array<string, mixed>> $banners */
        $banners = CatalogCache::rememberBanners(function () {
            return $this->activeBanners();
        });

        return $banners;
    }

    public function flush(): void
    {
        CatalogCache::flushHomepage();
    }

    /**
     * Hero display variant chosen by the merchant. Unknown values
     * fall back to the editorial split hero.
     */
    public function heroDisplay(): string
    {
        $display = $this->settings?->get('homepage.hero_display') ?? 'split';

        return in_array($display, self::HERO_DISPLAYS, true) ? $display : 'split';
    }

    public function saveHeroDisplay(string $display): void
    {
        $this->settings->set('homepage.hero_display', $display, 'homepage');
        $this->flush();
    }

    /**
     * Active slides, localized and ordered, with safe links.
     *
     * @return array<int, array<string, mixed>>
     */
    public function slides(): array
    {
        /** @var array<int, array<string, mixed>> $slides */
        $slides = CatalogCache::rememberHero(function () {
            return HeroSlide::active()->ordered()->get()->map(fn (HeroSlide $slide) => [
                'id' => $slide->id,
                'eyebrow' => $slide->displayEyebrow(),
                'title' => $slide->displayTitle(),
                'subtitle' => $slide->displaySubtitle(),
                'cta_label' => $slide->displayCtaLabel(),
                'cta_link' => MenuService::safeUrl($slide->cta_link) ?? '/products',
                'image' => $slide->image,
                'layout' => in_array($slide->layout, HeroSlide::LAYOUTS, true) ? $slide->layout : 'split',
                'show_eyebrow' => (bool) $slide->show_eyebrow,
                'show_title' => (bool) $slide->show_title,
                'show_subtitle' => (bool) $slide->show_subtitle,
                'show_button' => (bool) $slide->show_button,
            ])->all();
        });

        return $slides;
    }
}
