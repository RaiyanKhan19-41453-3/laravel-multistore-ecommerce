<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\MenuItem;
use App\Models\Product;
use App\Services\MenuService;
use App\Support\AdminStoreContext;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function __construct(
        protected MenuService $menus,
    ) {}

    public function index(): Response
    {
        $items = MenuItem::with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('title')])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        // The table reads child.children too; guarantee the key exists
        // so the page never renders undefined (black screen).
        $items->each(function (MenuItem $root): void {
            $root->children->each(fn (MenuItem $child) => $child->setRelation('children', collect()));
        });

        return Inertia::render('admin/menus/index', [
            'items' => $items,
            'targets' => $this->targets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateItem($request);

        MenuItem::create($validated);
        $this->menus->flush();

        return to_route('admin.menus.index');
    }

    public function update(Request $request, MenuItem $menu): RedirectResponse
    {
        $validated = $this->validateItem($request, $menu);

        $menu->update($validated);
        $this->menus->flush();

        return to_route('admin.menus.index');
    }

    public function toggle(MenuItem $menu): RedirectResponse
    {
        $menu->update(['is_active' => ! $menu->is_active]);
        $this->menus->flush();

        return to_route('admin.menus.index');
    }

    public function destroy(MenuItem $menu): RedirectResponse
    {
        $menu->delete();
        $this->menus->flush();

        return to_route('admin.menus.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request, ?MenuItem $menu = null): array
    {
        $storeId = app(CurrentStore::class)->scopeId();
        $adminStores = app(AdminStoreContext::class);
        $type = $request->input('type', $menu?->type ?? 'url');

        $targetTables = [
            'category' => 'categories',
            'brand' => 'brands',
            'product' => 'products',
            'page' => 'cms_pages',
        ];

        $rules = [
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'type' => ['required', Rule::in(MenuItem::TYPES)],
            'reference_id' => ['nullable', 'integer'],
            'url' => 'nullable|string|max:500',
            'click_behavior' => ['required', Rule::in(MenuItem::CLICK_BEHAVIORS)],
            'display' => ['required', Rule::in(MenuItem::DISPLAYS)],
            'promo_image' => 'nullable|string|max:500',
            'promo_title' => 'nullable|string|max:255',
            'promo_link' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('menu_items', 'id')
                    ->whereNull('parent_id')
                    ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
                    ->when($menu, fn ($q) => $q->where('id', '!=', $menu->id)),
            ],
        ];

        if (isset($targetTables[$type])) {
            $rules['reference_id'] = ['required', $adminStores->existsInStore($targetTables[$type], $storeId)];
        }

        if ($type === 'url') {
            $rules['url'] = [
                'required',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! MenuService::safeUrl(is_string($value) ? $value : null)) {
                        $fail('The link must be a site path (e.g. /products) or an http(s) URL.');
                    }
                },
            ];
        }

        $rules['promo_link'] = [
            'nullable',
            'string',
            'max:500',
            function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== null && $value !== '' && ! MenuService::safeUrl(is_string($value) ? $value : null)) {
                    $fail('The promo link must be a site path or an http(s) URL.');
                }
            },
        ];

        $validated = $request->validate($rules);

        $validated['type'] = $type;
        $validated['reference_id'] = $type === 'url' ? null : ($validated['reference_id'] ?? null);
        $validated['url'] = $type === 'url' ? MenuService::safeUrl($validated['url'] ?? null) : null;

        $parentId = $validated['parent_id'] ?? null;

        if ($parentId !== null && $menu && $menu->children()->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => 'An item with submenu items cannot be moved under another item.',
            ]);
        }

        $validated['parent_id'] = $parentId;

        return $validated;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function targets(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name'])->toArray(),
            'brands' => Brand::orderBy('name')->get(['id', 'name'])->toArray(),
            'products' => Product::orderBy('name')->limit(200)->get(['id', 'name'])->toArray(),
            'pages' => CmsPage::orderBy('title')->get(['id', 'title'])->toArray(),
        ];
    }
}
