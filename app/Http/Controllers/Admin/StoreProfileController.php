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
                'email' => $this->settings->get('store.email') ?? '',
                'phone' => $this->settings->get('store.phone') ?? '',
                'address' => $this->settings->get('store.address') ?? '',
                'city' => $this->settings->get('store.city') ?? '',
                'google_tag_id' => $this->settings->get('marketing.google_tag_id') ?? '',
                'google_site_verification' => $this->settings->get('marketing.google_site_verification') ?? '',
                'meta_pixel_id' => $this->settings->get('marketing.meta_pixel_id') ?? '',
                'meta_title' => $this->settings->get('store.meta_title') ?? '',
                'meta_description' => $this->settings->get('store.meta_description') ?? '',
                'theme_color' => $this->settings->get('store.theme_color') ?? '',
                'favicon' => $this->settings->get('store.favicon'),
                'og_image' => $this->settings->get('store.og_image'),
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
            'theme_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'google_tag_id' => ['nullable', 'string', 'regex:/^(GTM-[A-Z0-9]+|G-[A-Z0-9]+)$/'],
            'google_site_verification' => 'nullable|string|max:255|regex:/^[A-Za-z0-9_-]+$/',
            'meta_pixel_id' => 'nullable|string|regex:/^[0-9]{5,20}$/',
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
            'store.theme_color' => $request->input('theme_color'),
            'marketing.google_tag_id' => $request->input('google_tag_id'),
            'marketing.google_site_verification' => $request->input('google_site_verification'),
            'marketing.meta_pixel_id' => $request->input('meta_pixel_id'),
        ], fn ($value) => $value !== null);

        if (! empty($values)) {
            $stringValues = array_map(fn ($value) => (string) $value, $values);

            foreach ($stringValues as $key => $value) {
                [$group] = explode('.', $key, 2) + [null];
                $this->settings->set($key, $value, $group ?? 'general');
            }
        }

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
