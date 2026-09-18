---
paths:
  - routes/admin.php
  - routes/web.php
  - routes/api.php
---

# Routes

## Admin per-route permissions
Admin routes use per-route permission middleware (orders.view/orders.manage, catalog.view/catalog.manage, customers, discounts, inventory, shipping, reports, settings, reviews, pages, zatca, activity; audit and destructive ops stay role:super-admin). Spatie v6 pipe means ANY. Staff roles live in PermissionSeeder::staffRoles; tests must seed via ensureStaffPermissions()/createStaffUser() since RefreshDatabase wipes roles.

## Storefront shells use client-side gates
Storefront account/wishlist pages must be public Inertia shells with client-side login redirect (getUser() null -> /account/login?redirect=...). Session `auth` middleware does NOT match storefront token-cookie users and bounces them to admin/login. API endpoints still enforce auth:sanctum; pages must also redirect when a request 401s (expired token).

## API throttle groups and PII stripping
The auth:sanctum group carries throttle:60,1 (reviews, wishlist, addresses, orders, cart merge). Cart/checkout stay on their own api.cart + throttle:30,1 group; guest lookup stays at throttle:5,1. Never remove gateway_response hiding on customer-facing order payloads (index/show/lookup all strip it).
