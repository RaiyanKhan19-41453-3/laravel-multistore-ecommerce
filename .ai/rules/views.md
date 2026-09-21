---
paths:
  - 'resources/views/**'
---

# Views

## Use @php blocks, never stacked @php() lines
Write multi-statement PHP in Blade with @php/@endphp blocks only. Consecutive single-line @php() directives miscompile (statements silently dropped), which broke every storefront page.
