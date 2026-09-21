<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StoreProfileController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/store-profile/index', [
            'profile' => [
                'name' => $this->settings->get('store.name') ?? '',
                'tagline' => $this->settings->get('store.tagline') ?? '',
                'logo' => $this->settings->get('store.logo'),
                'show_store_name' => $this->settings->get('store.show_store_name', '1') !== '0',
                'email' => $this->settings->get('store.email') ?? '',
                'phone' => $this->settings->get('store.phone') ?? '',
                'address' => $this->settings->get('store.address') ?? '',
                'city' => $this->settings->get('store.city') ?? '',
                'google_tag_id' => $this->settings->get('marketing.google_tag_id') ?? '',
                'google_site_verification' => $this->settings->get('marketing.google_site_verification') ?? '',
                'meta_pixel_id' => $this->settings->get('marketing.meta_pixel_id') ?? '',
                'twitter_handle' => $this->settings->get('marketing.twitter_handle') ?? '',
                'meta_title' => $this->settings->get('store.meta_title') ?? '',
                'meta_description' => $this->settings->get('store.meta_description') ?? '',
                'favicon' => $this->settings->get('store.favicon'),
                'og_image' => $this->settings->get('store.og_image'),
                'og_image_alt' => $this->settings->get('social.og_image_alt') ?? '',
                'twitter_card' => $this->settings->get('social.twitter_card', 'summary_large_image'),
                'robots_noindex' => $this->settings->get('store.robots_noindex', '0') !== '0',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'google_tag_id' => ['nullable', 'string', 'regex:/^(GTM-[A-Z0-9]+|G-[A-Z0-9]+)$/'],
            'google_site_verification' => 'nullable|string|max:255|regex:/^[A-Za-z0-9_-]+$/',
            'meta_pixel_id' => 'nullable|string|regex:/^[0-9]{5,20}$/',
            'twitter_handle' => 'nullable|string|regex:/^@[A-Za-z0-9_]{1,15}$/',
            'twitter_card' => 'nullable|string|in:summary,summary_large_image',
            'og_image_alt' => 'nullable|string|max:255',
            'robots_noindex' => 'nullable|boolean',
            'show_store_name' => 'nullable|boolean',
            'logo_file' => 'nullable|file|mimes:jpeg,png,webp,svg|max:2048',
            'remove_logo' => 'nullable|boolean',
            'favicon_file' => 'nullable|file|mimes:jpeg,png,webp,svg,ico|max:1024',
            'remove_favicon' => 'nullable|boolean',
            'og_image_file' => 'nullable|file|mimes:jpeg,png,webp|max:2048',
            'remove_og_image' => 'nullable|boolean',
        ]);

        $this->syncFile($request, 'logo_file', 'remove_logo', 'store.logo', 'logos');
        $this->syncFile($request, 'favicon_file', 'remove_favicon', 'store.favicon', 'favicons');
        $this->syncFile($request, 'og_image_file', 'remove_og_image', 'store.og_image', 'social');

        $values = array_filter([
            'store.name' => $request->input('name'),
            'store.tagline' => $request->input('tagline'),
            'store.email' => $request->input('email'),
            'store.phone' => $request->input('phone'),
            'store.address' => $request->input('address'),
            'store.city' => $request->input('city'),
            'store.meta_title' => $request->input('meta_title'),
            'store.meta_description' => $request->input('meta_description'),
            'marketing.google_tag_id' => $request->input('google_tag_id'),
            'marketing.google_site_verification' => $request->input('google_site_verification'),
            'marketing.meta_pixel_id' => $request->input('meta_pixel_id'),
            'marketing.twitter_handle' => $request->input('twitter_handle'),
            'social.twitter_card' => $request->input('twitter_card'),
            'social.og_image_alt' => $request->input('og_image_alt'),
        ], fn ($value) => $value !== null);

        if (! empty($values)) {
            $stringValues = array_map(fn ($value) => (string) $value, $values);

            foreach ($stringValues as $key => $value) {
                [$group] = explode('.', $key, 2) + [null];
                $this->settings->set($key, $value, $group ?? 'general');
            }
        }

        // Checkbox semantics: absent means off, so persist explicitly
        // instead of leaving a stale value behind.
        $this->settings->set(
            'store.show_store_name',
            $request->boolean('show_store_name') ? '1' : '0',
            'store'
        );
        $this->settings->set(
            'store.robots_noindex',
            $request->boolean('robots_noindex') ? '1' : '0',
            'store'
        );

        return to_route('admin.store-profile.index')->with('success', __('store.saved'));
    }

    /**
     * Branding file lifecycle: upload replaces (deleting the old file),
     * remove_* clears it. The path is the only thing persisted.
     */
    private function syncFile(Request $request, string $fileKey, string $removeKey, string $settingKey, string $dir): void
    {
        $old = $this->settings->get($settingKey);

        $drop = function () use ($old): void {
            if ($old) {
                Storage::disk('public')->delete(ltrim((string) preg_replace('#^/storage/#', '', $old), '/'));
            }
        };

        if ($request->boolean($removeKey)) {
            $drop();
            $this->settings->set($settingKey, null, 'store');

            return;
        }

        $file = $request->file($fileKey);

        if ($file && $file->isValid()) {
            $drop();
            $path = $file->store($dir, 'public');
            $this->settings->set($settingKey, '/storage/'.$path, 'store');
        }
    }
}
