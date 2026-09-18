---
paths:
  - 'app/Services/CartService.php, app/Services/OrderService.php, app/Services/ShippingService.php'
---

# Services Services

## Per-store carts and checkout binding
Phase 3 carts: one active cart per owner per store — carts.guest_token unique is now (store_id, guest_token) (legacy name carts_new_guest_token_unique, resolve via getIndexListing). CartService getOrCreate takes optional storeId defaulting to CurrentStore::scopeId(); addItem rejects cross-store items (stale-cookie carts) and adopts a store for legacy storeless carts. Orders/items take store explicitly from the cart, shipping rates validated against the cart store. Cart/CartItem/Order/OrderItem have store_id fillable (needed for firstOrCreate/create with store_id).
