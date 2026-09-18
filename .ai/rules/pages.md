---
paths:
  - 'resources/js/pages/**'
---

# Pages

## Rebuild Vite manifest for new pages in tests
Feature tests render Inertia pages through the Vite manifest, so run `npm run build` after adding/renaming any page (e.g. admin/settings/index) or the test fails with "Unable to locate file in Vite manifest". Rebuild again before finishing if pages changed.

## Frontend must stay tsc-clean
Keep `npx tsc --noEmit` clean (CI gates it via `npm run types`). useForm setData only accepts top-level keys in types — use a typed helper for nested dotted paths instead of casts. New literal state (false/0) in useForm needs an explicit generic or setData rejects the update.
