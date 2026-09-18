---
paths:
  - 'app/Http/Controllers/Admin/**, app/Http/Controllers/Api/**, app/Services/CatalogService.php'
---

# Api Services

## Per-store uniques and read scoping
Phase 2 multistore: global uniques are now per-store composites (store_id, slug/sku/code/order_number). Admin validators must use Rule::unique()->where('store_id', CurrentStore::scopeId()) with ignore() on update. Storefront reads (CatalogService, Api Product/Category/Brand/Cms/Wishlist/Review/Cart, coupon lookup, order lookup) must wrap base queries with CurrentStore::applyScope(). Child records inherit the parent store (variants, images), never CurrentStore alone. Carts stay global-single for now (Phase 3).
