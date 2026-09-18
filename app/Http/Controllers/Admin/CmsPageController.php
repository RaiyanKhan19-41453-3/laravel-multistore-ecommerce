<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CmsPageController extends Controller
{
    public function index(): Response
    {
        $pages = CmsPage::orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return Inertia::render('admin/pages/index', [
            'pages' => $pages,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/pages/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $storeId = app(CurrentStore::class)->scopeId();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('cms_pages', 'slug')->where('store_id', $storeId)],
            'body' => 'nullable|string',
            'body_ar' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['title']);

        CmsPage::create($validated);

        return to_route('admin.pages.index');
    }

    public function edit(CmsPage $page): Response
    {
        return Inertia::render('admin/pages/edit', [
            'page' => $page,
        ]);
    }

    public function update(Request $request, CmsPage $page): RedirectResponse
    {
        $storeId = app(CurrentStore::class)->scopeId();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('cms_pages', 'slug')->ignore($page->id)->where('store_id', $storeId)],
            'body' => 'nullable|string',
            'body_ar' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['title']);

        $page->update($validated);

        return to_route('admin.pages.index');
    }

    public function toggle(CmsPage $page): RedirectResponse
    {
        $page->update(['is_published' => ! $page->is_published]);

        return to_route('admin.pages.index');
    }

    public function destroy(CmsPage $page): RedirectResponse
    {
        $page->delete();

        return to_route('admin.pages.index');
    }
}
