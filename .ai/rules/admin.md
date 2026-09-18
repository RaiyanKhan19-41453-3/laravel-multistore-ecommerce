---
paths:
  - 'app/Http/Middleware/EnsureAdminStoreAccess.php, app/Http/Controllers/Admin/**'
---

# Admin

## Admin record-level store guard
Phase 6 record guard: EnsureAdminStoreAccess runs after ResolveAdminStore in the admin group and 404s any route-model-bound record whose store_id mismatches the explicit selection (platform view skips). Children with parent-ownership checks (images, variants, coupons) are transitively protected. Cross-record links in bodies must use AdminStoreContext::existsInStore() anchored to the parent record's store (anchorStoreId), and child records inherit the parent store explicitly (variants, images, coupons).
