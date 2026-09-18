<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\MenuItem;
use App\Models\Product;
use App\Scopes\BelongsToStore;

class MenuService
{
    /**
     * Active, ordered menu tree for the current store, with labels
     * localized and links resolved. Dead targets are skipped.
     * Rendered depth is capped at two levels by construction.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        /** @var array<int, array<string, mixed>> $tree */
        $tree = CatalogCache::rememberMenus(function () {
            $roots = MenuItem::active()->roots()
                ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('title')])
                ->get();

            $nodes = [];

            foreach ($roots as $root) {
                $node = $this->toNode($root);

                if ($node === null) {
                    continue;
                }

                foreach ($root->children as $child) {
                    $childNode = $this->toNode($child);

                    if ($childNode !== null) {
                        $node['children'][] = $childNode;
                    }
                }

                $node['has_children'] = ! empty($node['children']);
                $nodes[] = $node;
            }

            return $nodes;
        });

        return $tree;
    }

    public function flush(): void
    {
        CatalogCache::flushMenus();
    }

    /**
     * Resolve a menu item to its storefront URL, or null when the
     * target is missing, inactive, or the type is unknown.
     */
    public function resolveUrl(MenuItem $item): ?string
    {
        return match ($item->type) {
            'category' => $this->sluggedUrl(Category::class, $item->reference_id, '/categories/', $item->store_id),
            'brand' => $this->sluggedUrl(Brand::class, $item->reference_id, '/brands/', $item->store_id),
            'product' => $this->sluggedUrl(Product::class, $item->reference_id, '/products/', $item->store_id),
            'page' => $this->sluggedUrl(CmsPage::class, $item->reference_id, '/pages/', $item->store_id, true),
            'url' => self::safeUrl($item->url),
            default => null,
        };
    }

    /**
     * Fetch a live, same-store target by id and prefix its slug.
     * Anchored to the menu item's own store, never ambient context.
     * CmsPage gates on is_published instead of is_active.
     */
    private function sluggedUrl(string $model, mixed $id, string $prefix, ?int $storeId, bool $isPage = false): ?string
    {
        if (! is_numeric($id)) {
            return null;
        }

        $query = $model::withoutGlobalScope(BelongsToStore::class)->whereKey((int) $id);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $record = $query->first();

        if (! $record) {
            return null;
        }

        if ($isPage ? ! $record->is_published : ! $record->is_active) {
            return null;
        }

        return $record->slug ? $prefix.$record->slug : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toNode(MenuItem $item): ?array
    {
        $url = $this->resolveUrl($item);

        if ($url === null) {
            return null;
        }

        $promo = null;

        if ($item->promo_title || $item->promo_image) {
            $promo = [
                'image' => $item->promo_image,
                'title' => $item->promo_title,
                'link' => self::safeUrl($item->promo_link) ?? $url,
            ];
        }

        return [
            'id' => $item->id,
            'title' => $item->displayName(),
            'url' => $url,
            'click_behavior' => $item->click_behavior,
            'display' => $item->display,
            'has_children' => false,
            'children' => [],
            'promo' => $promo,
        ];
    }

    /**
     * Custom URLs must be same-site paths or http(s) links.
     * Rejects javascript:, data:, and protocol-relative URLs.
     */
    public static function safeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return null;
    }
}
