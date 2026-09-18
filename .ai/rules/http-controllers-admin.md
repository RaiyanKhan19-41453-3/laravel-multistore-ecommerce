---
paths:
  - app/Http/Controllers/Admin/StoreMemberController.php
  - 'app/Http/Controllers/Admin/*.php'
---

# Http Controllers Admin

## Member baseline roles and orphan strip
Member role lifecycle: adding a member grants a baseline Spatie role (owner gets store-owner unless super-admin; role-less staff get support) so they can actually open the admin — additive only, existing roles untouched. Removing the last membership strips store-owner so ex-members cannot fall back to the platform view. Last-owner removal is blocked (422). All wrapped so membership never fails on role seeding state.

## Anchor validation and child rows to the parent store
Store-scoped uniqueness validation must use the partition the row lands in (parent record's store, e.g. discount->store_id for coupons), never CurrentStore::scopeId(). Child rows inherit the parent store explicitly (shipments from order->store_id); the central auto-fill is only a fallback.
