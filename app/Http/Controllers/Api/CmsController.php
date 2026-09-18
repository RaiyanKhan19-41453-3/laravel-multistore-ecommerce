<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Services\CatalogCache;
use Illuminate\Http\JsonResponse;

class CmsController extends Controller
{
    public function index(): JsonResponse
    {
        $pages = CatalogCache::rememberPages(fn () => CmsPage::published()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'title', 'title_ar', 'slug', 'meta_title', 'meta_description']));

        return response()->json([
            'success' => true,
            'data' => $pages,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::published()->where('slug', $slug)->firstOrFail();

        $locale = app()->getLocale();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $page->id,
                'title' => $page->displayName(),
                'slug' => $page->slug,
                'body' => $page->displayBody(),
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
            ],
        ]);
    }
}
