---
paths:
  - 'app/Models/**, app/Support/**, app/Http/Middleware/ResolveStore.php'
---

# Http Middleware

## Multistore foundation stays additive
Multistore Phase 1 is additive only: stores + store_user tables, nullable store_id on 28 tenant tables (null = default store), CurrentStore singleton + ResolveStore middleware (header X-Store-Slug/Id > ?store= > custom domain > subdomain > default, never 404s). New tenant rows auto-fill store_id centrally in AppServiceProvider, not per-model traits. Settings/global uniques still global — per-store enforcement is a later phase. Cache keys must be store-prefixed (CatalogCache::prefix), uploads under products/{storeId}/{productId}.
