---
paths:
  - resources/js/pages/store/products/index.tsx
---

# Products

## Products listing is server-rendered
Exception to the client-side fetch pattern: /products is server-rendered by Store\ProductController, which passes products/filters/brands/categories as Inertia props from CatalogService. Filter and pagination navigation reuse those props via router.get. Detail/category/brand/search pages still fetch client-side.
