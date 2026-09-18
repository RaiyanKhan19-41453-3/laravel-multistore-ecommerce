---
paths:
  - 'app/Support/*.php, app/Http/Middleware/ResolveAdminStore.php'
---

# Support Http Middleware

## Admin store context and listing scopes
Admin store context: AdminStoreContext singleton holds session selection (admin_store_id) + per-request header override. Null selection = platform view (unscoped, legacy behavior). ResolveAdminStore must clearOverride() on every request or the header leaks into later requests via the shared singleton (tests/Octane). Admin listings scope via AdminStoreContext::scope() (explicit-selection only); creations/validation/settings ride on CurrentStore which the middleware points at the selected store. Record-level binding checks are still open.
