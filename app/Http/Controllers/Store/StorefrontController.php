<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Api\BrandController as ApiBrandController;
use App\Http\Controllers\Api\CategoryController as ApiCategoryController;
use App\Http\Controllers\Api\PaymentMethodController as ApiPaymentMethodController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use App\Services\HomepageService;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Server-rendered storefront pages: the data used to arrive through eight
 * client fetches after paint. Composing it here means first paint is
 * complete. Read paths reuse the API controllers and services so the
 * JSON API and the pages can never disagree.
 */
class StorefrontController extends Controller
{
    public function __construct(
        protected CatalogService $catalog,
        protected MenuService $menus,
        protected HomepageService $homepage,
    ) {}

    public function home(): Response
    {
        $featured = $this->catalog->featured(12);

        $topRated = collect($this->catalog->paginate([
            'sort' => 'rating',
            'direction' => 'desc',
            'per_page' => 4,
        ])->items())
            ->filter(fn ($product) => ($product['review_summary']['total'] ?? 0) > 0)
            ->take(4)
            ->values()
            ->all();

        $saleItems = array_slice($this->catalog->paginate([
            'on_sale' => 1,
            'per_page' => 10,
        ])->items(), 0, 10);

        $newArrivals = array_slice($this->catalog->paginate([
            'sort' => 'created_at',
            'direction' => 'desc',
            'per_page' => 8,
        ])->items(), 0, 8);

        $categories = array_slice($this->apiData(app(ApiCategoryController::class)->index()), 0, 8);
        $brands = array_slice($this->apiData(app(ApiBrandController::class)->index()), 0, 12);
        $payMethods = $this->apiData(app(ApiPaymentMethodController::class)->index())['methods'] ?? [];

        return Inertia::render('store/index', [
            'featured' => $featured,
            'categories' => $categories,
            'brands' => $brands,
            'topRated' => $topRated,
            'saleItems' => $saleItems,
            'newArrivals' => $newArrivals,
            'payMethods' => $payMethods,
            'blocks' => $this->homepage->blocks(),
            'heroDisplay' => $this->homepage->heroDisplay(),
            'slides' => $this->homepage->slides(),
        ]);
    }

    public function category(string $slug): Response
    {
        return Inertia::render('store/categories/show', [
            'slug' => $slug,
            'category' => $this->apiData(app(ApiCategoryController::class)->show($slug)),
        ]);
    }

    public function brand(string $slug): Response
    {
        return Inertia::render('store/brands/show', [
            'slug' => $slug,
            'brand' => $this->apiData(app(ApiBrandController::class)->show($slug)),
        ]);
    }

    public function product(string $slug): Response
    {
        return Inertia::render('store/products/show', [
            'slug' => $slug,
            'product' => $this->apiData(app(ApiProductController::class)->show($slug)),
        ]);
    }

    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    private function apiData(JsonResponse $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = $response->getData(true);

        /** @var array<string, mixed>|array<int, mixed> */
        return $decoded['data'] ?? [];
    }
}
