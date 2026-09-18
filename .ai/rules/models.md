---
paths:
  - 'app/Models/Shipping*.php'
  - app/Models/CmsPage.php
  - 'app/Models/*.php'
---

# Models

## Shipping zone matching is case-insensitive
ShippingZone::containsCity() lowercases both the input and stored cities for case-insensitive matching. API accepts any case for city names.

## CmsPage uses SoftDeletes
CmsPage model uses SoftDeletes. Admin delete soft-deletes the record. Use assertSoftDeleted in tests, not assertDatabaseMissing. Scope `published()` filters is_published=true.

## CMS HTML is trusted-staff only
CMS bodies render as raw HTML on the storefront by design (admin-authored rich text, no sanitizer installed). Only grant the pages permission to fully trusted staff: a stored script would run for every visitor and for other admins. If CMS editing ever goes to less-trusted roles, add HTMLPurifier plus CSP headers first.

## BelongsToStore global scope: only top-level tenant models
Only add `BelongsToStore` global scope to top-level tenant models (Product, Order, Cart, Category, Brand, etc.) that are independently queried and identified by store. Do NOT add it to child/junction models (Inventory, InventoryMovement, CartItem, OrderItem, CouponRedemption, ProductImage) — these are always accessed through a parent relationship which already carries store scoping. Adding the scope to children causes factory-created records (no CurrentStore context) to get mismatched store_ids, breaking queries.

When you need a child model's parent store_id for operations like file paths, use `ParentModel::withoutGlobalScope(BelongsToStore::class)->whereKey(...)` to bypass the scope.
