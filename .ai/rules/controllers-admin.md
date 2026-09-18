---
paths:
  - 'database/seeders/PermissionSeeder.php, app/Support/AdminStoreContext.php, app/Http/Controllers/Admin/StoreMemberController.php'
---

# Controllers Admin

## Store staff roles and membership lock
Phase 7 staff model: store-owner Spatie role holds all store domains but no platform powers; granted on merchant signup (guarded by Role::exists + try/catch). Non-super-admin users WITH memberships auto-lock to their first store (never platform view); staff without memberships keep legacy platform view. Store members managed via /admin/store-members (owner or super-admin only, selected store required, last owner protected). Membership default memoized per user id for Octane safety.
