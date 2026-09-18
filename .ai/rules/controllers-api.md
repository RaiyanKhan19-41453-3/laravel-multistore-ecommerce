---
paths:
  - 'app/Models/Store.php, app/Services/ImageService.php, app/Http/Controllers/Api/StoreController.php'
---

# Controllers Api

## Store scoping invariants
Store::default() prefers active stores (inactive first row must not become platform default). Store slugs: uniqueSlug() must check withTrashed since the DB unique index still holds soft-deleted slugs. Image uploads follow the parent product's store_id for both directory and record, never CurrentStore alone. Never auto-attach users to stores on registration — only StoreController attaches the owner on creation.
