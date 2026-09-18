<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;
use App\Support\CurrentStore;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class SitemapController extends Controller
{
    /**
     * Store-scoped sitemap: static storefront routes plus every live
     * product, category, brand, and published page. Cached briefly;
     * flushed by model observers on catalog changes.
     */
    public function index(): Response
    {
        $storeId = app(CurrentStore::class)->scopeId();
        $key = 'sitemap:'.($storeId ?? 'global').':'.app()->getLocale();

        $xml = Cache::remember($key, 3600, function () {
            $urls = [
                $this->entry('/', 1.0, now()),
                $this->entry('/products', 0.9, now()),
            ];

            $products = Product::active()->orderByDesc('updated_at')->limit(5000)->get(['slug', 'updated_at']);
            foreach ($products as $product) {
                $urls[] = $this->entry("/products/{$product->slug}", 0.8, $product->updated_at);
            }

            $categories = Category::active()->orderByDesc('updated_at')->limit(1000)->get(['slug', 'updated_at']);
            foreach ($categories as $category) {
                $urls[] = $this->entry("/categories/{$category->slug}", 0.7, $category->updated_at);
            }

            $brands = Brand::active()->orderByDesc('updated_at')->limit(1000)->get(['slug', 'updated_at']);
            foreach ($brands as $brand) {
                $urls[] = $this->entry("/brands/{$brand->slug}", 0.6, $brand->updated_at);
            }

            $pages = CmsPage::published()->orderByDesc('updated_at')->limit(500)->get(['slug', 'updated_at']);
            foreach ($pages as $page) {
                $urls[] = $this->entry("/pages/{$page->slug}", 0.5, $page->updated_at);
            }

            return view('sitemap.index', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * @return array{loc: string, priority: float, lastmod: string}
     */
    private function entry(string $path, float $priority, mixed $lastmod): array
    {
        $time = $lastmod instanceof \DateTimeInterface ? $lastmod : now();

        return [
            'loc' => URL::to($path),
            'priority' => $priority,
            'lastmod' => $time->toAtomString(),
        ];
    }
}
