<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\AdminStoreContext;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $adminStores = app(AdminStoreContext::class);

        $categories = $adminStores->scope(Category::with('parent'))
            ->withCount('children')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $allCategories = $adminStores->scope(Category::orderBy('name'))->get(['id', 'name', 'parent_id']);

        return Inertia::render('admin/categories/index', [
            'categories' => $categories,
            'allCategories' => $allCategories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $storeId = app(CurrentStore::class)->scopeId();
        $adminStores = app(AdminStoreContext::class);

        $validated = $request->validate([
            'parent_id' => ['nullable', $adminStores->existsInStore('categories', $storeId)],
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->where('store_id', $storeId)],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);

        Category::create($validated);

        return to_route('admin.categories.index');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $storeId = app(CurrentStore::class)->scopeId();
        $adminStores = app(AdminStoreContext::class);

        $validated = $request->validate([
            'parent_id' => ['nullable', $adminStores->existsInStore('categories', $adminStores->anchorStoreId($category))],
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category->id)->where('store_id', $storeId)],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);

        $category->update($validated);

        return to_route('admin.categories.index');
    }

    public function toggle(Category $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return to_route('admin.categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return to_route('admin.categories.index');
    }
}
