---
paths:
  - 'resources/js/pages/store/**/*.tsx'
---

# Store

## Storefront page data fetching pattern
Browse pages (home, categories, brands, product detail, listing) receive their data as server-side Inertia props from `Store\StorefrontController`, which reuses the API controllers and services so both surfaces agree. Layout chrome (menus, nav categories) arrives via shared `nav` props for non-admin routes. Only interactive flows stay client-side: typed search, paginated review lists, cart/checkout/account mutations, and the mini-cart badge. Routes only pass slugs. Pages use usePage for shared props only (store, locale, direction).
