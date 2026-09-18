<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        protected CatalogService $catalog = new CatalogService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only([
            'search',
            'brand_id',
            'category_id',
            'is_featured',
            'min_price',
            'max_price',
            'sort',
            'direction',
            'attribute_values',
            'discount_id',
        ]);

        return Inertia::render('store/products/index', [
            'products' => $this->catalog->paginate($filters),
            // Cast to object: an empty $request->only() is PHP [] which
            // serializes to JSON [] — and JS [].sort is Array.prototype.sort,
            // which useState() would invoke as a lazy initializer (crash).
            'filters' => (object) $filters,
            'brands' => $this->catalog->brandOptions(),
            'categories' => $this->catalog->categoryOptions(),
        ]);
    }
}
