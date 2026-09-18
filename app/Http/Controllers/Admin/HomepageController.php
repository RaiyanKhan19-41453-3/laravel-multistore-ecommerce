<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\HeroSlide;
use App\Models\HomepageSection;
use App\Services\HomepageService;
use App\Services\MenuService;
use App\Support\AdminStoreContext;
use App\Support\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HomepageController extends Controller
{
    public function __construct(
        protected HomepageService $homepage,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/homepage/index', [
            'blocks' => $this->homepage->layoutForAdmin(),
            'heroDisplay' => $this->homepage->heroDisplay(),
            'displays' => HomepageService::HERO_DISPLAYS,
            'slides' => HeroSlide::ordered()->get(),
            'banners' => Banner::ordered()->get(),
            'bannerLayouts' => Banner::LAYOUTS,
            'bannerTextLayouts' => Banner::TEXT_LAYOUTS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $storeId = app(CurrentStore::class)->scopeId();
        $adminStores = app(AdminStoreContext::class);

        $validated = $request->validate([
            'hero_display' => ['required', Rule::in(HomepageService::HERO_DISPLAYS)],
            'blocks' => 'required|array|min:1',
            'blocks.*.type' => ['required', Rule::in(['section', 'banner'])],
            'blocks.*.key' => ['nullable', Rule::in(HomepageSection::KEYS)],
            'blocks.*.banner_id' => ['nullable', 'integer', $adminStores->existsInStore('banners', $storeId)],
            'blocks.*.is_active' => 'required|boolean',
            'blocks.*.sort_order' => 'required|integer|min:0',
        ]);

        $seenSections = [];
        $seenBanners = [];

        foreach ($validated['blocks'] as $block) {
            if ($block['type'] === 'section') {
                if (empty($block['key']) || isset($seenSections[$block['key']])) {
                    throw ValidationException::withMessages(['blocks' => 'Each section must appear exactly once.']);
                }
                $seenSections[$block['key']] = true;
            } else {
                if (empty($block['banner_id']) || isset($seenBanners[$block['banner_id']])) {
                    throw ValidationException::withMessages(['blocks' => 'Each banner must appear exactly once.']);
                }
                $seenBanners[$block['banner_id']] = true;
            }
        }

        $this->homepage->saveHeroDisplay($validated['hero_display']);
        $this->homepage->saveBlocks($validated['blocks']);

        return to_route('admin.homepage.index');
    }

    public function storeSlide(Request $request): RedirectResponse
    {
        $validated = $this->validateSlide($request);

        $validated['image'] = $this->storeSlideImage($request, null);
        HeroSlide::create($validated);
        $this->homepage->flush();

        return to_route('admin.homepage.index');
    }

    public function updateSlide(Request $request, HeroSlide $slide): RedirectResponse
    {
        $validated = $this->validateSlide($request);

        $image = $this->storeSlideImage($request, $slide);

        if ($image !== null) {
            $validated['image'] = $image;
        } elseif ($request->boolean('remove_image')) {
            if ($slide->image) {
                Storage::disk('public')->delete(ltrim((string) preg_replace('#^/storage/#', '', $slide->image), '/'));
            }
            $validated['image'] = null;
        }

        $slide->update($validated);
        $this->homepage->flush();

        return to_route('admin.homepage.index');
    }

    public function toggleSlide(HeroSlide $slide): RedirectResponse
    {
        $slide->update(['is_active' => ! $slide->is_active]);
        $this->homepage->flush();

        return to_route('admin.homepage.index');
    }

    public function destroySlide(HeroSlide $slide): RedirectResponse
    {
        if ($slide->image) {
            Storage::disk('public')->delete(ltrim((string) preg_replace('#^/storage/#', '', $slide->image), '/'));
        }

        $slide->delete();
        $this->homepage->flush();

        return to_route('admin.homepage.index');
    }

    public function storeBanner(Request $request): RedirectResponse
    {
        $validated = $this->validateBanner($request);
        $validated['images'] = $this->storeBannerImages($request, []);

        Banner::create($validated);
        $this->homepage->flush();

        return to_route('admin.homepage.index');
    }

    public function updateBanner(Request $request, Banner $banner): RedirectResponse
    {
        $validated = $this->validateBanner($request);
        $validated['images'] = $this->mergeBannerImages($request, $banner);

        $banner->update($validated);
        $this->homepage->flush();

        return to_route('admin.homepage.index');
    }

    public function toggleBanner(Banner $banner): RedirectResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);
        $this->homepage->flush();

        return to_route('admin.homepage.index');
    }

    public function destroyBanner(Banner $banner): RedirectResponse
    {
        $this->deleteFiles($banner->imageList());
        $banner->delete();
        $this->homepage->flush();

        return to_route('admin.homepage.index');
    }

    /**
     * Resolve any pasted video/page link into a safe embed URL without
     * leaving the dialog. YouTube links (watch, Shorts, share, embed)
     * normalize to the privacy-friendly embed form and pick up the
     * video title via oEmbed; other https embeds pass through.
     */
    public function fetchEmbed(Request $request): JsonResponse
    {
        $validated = $request->validate(['url' => 'required|string|max:2000']);

        $resolved = $this->normalizeEmbed(trim($validated['url']));

        if ($resolved === null) {
            throw ValidationException::withMessages(['url' => 'That link cannot be embedded. Paste a YouTube link or an https embed URL.']);
        }

        $title = null;

        if ($resolved['provider'] === 'youtube') {
            $title = $this->youTubeTitle($resolved['video_id']);
        }

        return response()->json([
            'embed_url' => $resolved['embed_url'],
            'title' => $title,
            'provider' => $resolved['provider'],
        ]);
    }

    /**
     * @return array{embed_url: string, video_id: string|null, provider: string}|null
     */
    private function normalizeEmbed(string $url): ?array
    {
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        $youTubeHosts = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be'];

        if (in_array($host, $youTubeHosts, true) || str_ends_with($host, '.youtube.com')) {
            $id = null;

            if ($host === 'youtu.be') {
                $id = $segments[0] ?? null;
            } elseif (($segments[0] ?? null) === 'shorts' || ($segments[0] ?? null) === 'embed' || ($segments[0] ?? null) === 'live') {
                $id = $segments[1] ?? null;
            } elseif ($path === '/watch' || str_starts_with($path, '/watch?') || $path === '/watch/') {
                parse_str($parts['query'] ?? '', $query);
                $id = is_string($query['v'] ?? null) ? $query['v'] : null;
            }

            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{6,}$/', $id)) {
                return null;
            }

            return [
                'embed_url' => "https://www.youtube.com/embed/{$id}",
                'video_id' => $id,
                'provider' => 'youtube',
            ];
        }

        if (! str_starts_with(strtolower($url), 'https://')) {
            return null;
        }

        return ['embed_url' => $url, 'video_id' => null, 'provider' => 'embed'];
    }

    private function youTubeTitle(string $videoId): ?string
    {
        try {
            $response = Http::timeout(8)->get('https://www.youtube.com/oembed', [
                'url' => "https://www.youtube.com/watch?v={$videoId}",
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $title = $response->json('title');

                return is_string($title) && $title !== '' ? $title : null;
            }
        } catch (\Throwable) {
            // Title is a nicety; the embed URL is what matters.
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBanner(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'subtitle_ar' => 'nullable|string|max:500',
            'button_label' => 'nullable|string|max:50',
            'button_label_ar' => 'nullable|string|max:50',
            'button_link' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && $value !== '' && ! MenuService::safeUrl(is_string($value) ? $value : null)) {
                        $fail('The button link must be a site path (e.g. /products) or an http(s) URL.');
                    }
                },
            ],
            'layout' => ['required', Rule::in(Banner::LAYOUTS)],
            'text_layout' => ['required', Rule::in(Banner::TEXT_LAYOUTS)],
            'show_title' => 'boolean',
            'show_subtitle' => 'boolean',
            'show_button' => 'boolean',
            'media_type' => ['nullable', Rule::in(Banner::MEDIA_TYPES)],
            'iframe_url' => [
                'nullable',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && $value !== '' && ! HomepageService::safeEmbedUrl(is_string($value) ? $value : null)) {
                        $fail('The embed must be an absolute https URL (e.g. a YouTube or Maps embed link).');
                    }
                },
            ],
            'kept_images' => 'nullable|array|max:4',
            'kept_images.*' => 'nullable|string|max:500',
            'image_files' => 'nullable|array|max:4',
            'image_files.*' => 'nullable|file|mimes:jpeg,png,webp|max:2048',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        unset($validated['kept_images'], $validated['image_files']);

        $link = $validated['button_link'] ?? null;
        $validated['button_link'] = $link ? MenuService::safeUrl($link) : null;

        $embed = $validated['iframe_url'] ?? null;
        $validated['iframe_url'] = $embed ? HomepageService::safeEmbedUrl($embed) : null;

        $validated['media_type'] = $validated['media_type'] ?? 'image';

        return $validated;
    }

    /**
     * @return array<int, string>
     */
    private function storeBannerImages(Request $request, array $kept): array
    {
        $paths = $kept;

        foreach ($request->file('image_files', []) ?? [] as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            if (count($paths) >= 4) {
                break;
            }

            $paths[] = '/storage/'.$file->store('banners', 'public');
        }

        return array_values($paths);
    }

    /**
     * Kept images must be a subset of the stored ones (no path injection);
     * removed ones are deleted from disk. Caps the total at 4.
     *
     * @return array<int, string>
     */
    private function mergeBannerImages(Request $request, Banner $banner): array
    {
        $existing = $banner->imageList();
        $kept = array_values(array_intersect(
            array_filter((array) $request->input('kept_images', [])),
            $existing
        ));

        $this->deleteFiles(array_diff($existing, $kept));

        return $this->storeBannerImages($request, $kept);
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('public')->delete(ltrim((string) preg_replace('#^/storage/#', '', $path), '/'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSlide(Request $request): array
    {
        $validated = $request->validate([
            'eyebrow' => 'nullable|string|max:100',
            'eyebrow_ar' => 'nullable|string|max:100',
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'subtitle_ar' => 'nullable|string|max:500',
            'cta_label' => 'nullable|string|max:50',
            'cta_label_ar' => 'nullable|string|max:50',
            'cta_link' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && $value !== '' && ! MenuService::safeUrl(is_string($value) ? $value : null)) {
                        $fail('The button link must be a site path (e.g. /products) or an http(s) URL.');
                    }
                },
            ],
            'show_eyebrow' => 'boolean',
            'show_title' => 'boolean',
            'show_subtitle' => 'boolean',
            'show_button' => 'boolean',
            'image_file' => 'nullable|file|mimes:jpeg,png,webp|max:2048',
            'remove_image' => 'nullable|boolean',
            'layout' => ['nullable', Rule::in(HeroSlide::LAYOUTS)],
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        unset($validated['image_file'], $validated['remove_image']);

        $link = $validated['cta_link'] ?? null;
        $validated['cta_link'] = $link ? MenuService::safeUrl($link) : null;

        return $validated;
    }

    /**
     * Store an uploaded slide image, or null when none was sent.
     * Old files are removed by the caller after deciding to replace.
     */
    private function storeSlideImage(Request $request, ?HeroSlide $slide): ?string
    {
        $file = $request->file('image_file');

        if (! $file || ! $file->isValid()) {
            return null;
        }

        if ($slide?->image) {
            Storage::disk('public')->delete(ltrim((string) preg_replace('#^/storage/#', '', $slide->image), '/'));
        }

        return '/storage/'.$file->store('hero', 'public');
    }
}
