---
paths:
  - 'resources/js/pages/store/**/*.tsx'
---

# Store

## Storefront page data fetching pattern
Storefront pages fetch data from API via client-side fetch() + useEffect, not from Inertia props. Routes only pass slugs. Pages use usePage for shared props only (store, locale, direction). This keeps web routes minimal and lets pages handle loading/error states.
