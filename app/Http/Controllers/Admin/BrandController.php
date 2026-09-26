<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): Response
    {
        $brands = Brand::orderBy('sort_order')
            ->orderBy('name')
            ->withCount('products')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/brands/index', [
            'brands' => $brands,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $storeId = app(CurrentStore::class)->scopeId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('brands', 'slug')->where('store_id', $storeId)],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'logo_file' => 'nullable|file|image|max:2048',
            'remove_logo' => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);

        if ($request->hasFile('logo_file')) {
            $validated['logo'] = $request->file('logo_file')->store('brand-logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $validated['logo'] = null;
        }

        unset($validated['logo_file'], $validated['remove_logo']);

        Brand::create($validated);

        return to_route('admin.brands.index');
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $storeId = app(CurrentStore::class)->scopeId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('brands', 'slug')->ignore($brand->id)->where('store_id', $brand->store_id ?? $storeId)],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'logo_file' => 'nullable|file|image|max:2048',
            'remove_logo' => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);

        if ($request->hasFile('logo_file')) {
            $this->deleteFile($brand->logo);
            $validated['logo'] = $request->file('logo_file')->store('brand-logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteFile($brand->logo);
            $validated['logo'] = null;
        }

        unset($validated['logo_file'], $validated['remove_logo']);

        $brand->update($validated);

        return to_route('admin.brands.index');
    }

    public function toggle(Brand $brand): RedirectResponse
    {
        $brand->update(['is_active' => ! $brand->is_active]);

        return to_route('admin.brands.index');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return to_route('admin.brands.index');
    }

    private function deleteFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        $relative = ltrim((string) preg_replace('#^(/storage/|storage/)#', '', $path), '/');

        if ($relative !== '') {
            Storage::disk('public')->delete($relative);
        }
    }
}
