---
paths:
  - 'app/Observers/*.php'
---

# Observers

## Catalog cache invalidation via observers
Featured/categories/brands/pages responses are cached per locale via CatalogCache. Product/Category/Brand/CmsPage observers flush the matching keys on saved/deleted/restored, so admin writes invalidate instantly. Flush both locales; never cache filtered/paginated product queries.
