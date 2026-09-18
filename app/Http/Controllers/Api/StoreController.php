<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreController extends Controller
{
    /**
     * Public directory of active stores (for the SaaS marketplace).
     */
    public function index(): JsonResponse
    {
        $stores = Store::query()
            ->active()
            ->orderBy('name')
            ->paginate(20, ['id', 'name', 'slug', 'country', 'currency']);

        return response()->json($stores);
    }

    /**
     * The store resolved for this request (subdomain / header / default).
     */
    public function current(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        if (! $store instanceof Store) {
            return response()->json(['message' => 'No store resolved.'], 404);
        }

        return response()->json($this->present($store));
    }

    public function show(Store $store): JsonResponse
    {
        if (! $store->is_active) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        return response()->json($this->present($store));
    }

    /**
     * Merchant self-service signup: anyone authenticated can open a store.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('stores', 'slug')],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],
            'locale' => ['nullable', 'string', 'max:5'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();

        $preset = null;

        if (! empty($validated['country'])) {
            $preset = SettingsService::presets()[strtoupper($validated['country'])] ?? null;
        }

        $store = Store::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? $this->uniqueSlug($validated['name']),
            'country' => strtoupper($validated['country'] ?? $this->presetValue($preset, 'store.country', 'BD')),
            'currency' => strtoupper($validated['currency'] ?? $this->presetValue($preset, 'store.currency', 'BDT')),
            'locale' => $validated['locale'] ?? $this->presetValue($preset, 'store.locale', 'en'),
            'timezone' => $validated['timezone'] ?? $this->presetValue($preset, 'store.timezone', 'Asia/Dhaka'),
            'is_active' => true,
            'owner_user_id' => $user?->id,
        ]);

        if ($user && ! $store->users()->whereKey($user->id)->exists()) {
            $store->users()->attach($user->id, ['role' => 'owner']);
        }

        // Merchants need admin access to their own store. The store-owner
        // role carries every store-level permission; isolation itself is
        // enforced by the admin store context, not by this role.
        try {
            if ($user && ! $user->hasRole('super-admin') && Role::where('name', 'store-owner')->exists()) {
                $user->assignRole('store-owner');
            }
        } catch (\Throwable) {
            // Store creation must never fail because of role seeding state.
        }

        return response()->json($this->present($store), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'country' => $store->country,
            'currency' => $store->currency,
            'locale' => $store->locale,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;
        $i = 2;

        // Include trashed rows: the DB unique index still holds their slugs,
        // so reusing one would blow up with a 500 instead of validating.
        while (Store::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * @param  array<string, string>|null  $preset
     */
    private function presetValue(?array $preset, string $key, string $default): string
    {
        return $preset[$key] ?? $default;
    }
}
