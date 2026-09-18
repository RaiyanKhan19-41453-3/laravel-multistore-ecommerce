---
paths:
  - 'app/Services/DashboardService.php, app/Http/Controllers/Admin/ReportController.php, app/Http/Controllers/Admin/StoreActivityController.php, app/Http/Controllers/Admin/ShippingController.php'
---

# Admin Http Controllers Admin

## Dashboard, reports, activity, shipping scoping
Phase 11 ops scoping: dashboard stats cached per store (dashboard.stats.store:{id}:v1) and all queries scoped to AdminStoreContext; reports (index + all CSV exports) scoped to selected store; store-activity carts/stats and clear-all-carts scoped; shipping methods/zones/rates listings scoped and zone duplicate/fallback checks per store (validateNoDuplicateCities now takes storeId, fallback unique per store+country). Platform view (no selection) stays global.
