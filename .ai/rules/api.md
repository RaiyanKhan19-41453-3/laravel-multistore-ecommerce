---
paths:
  - app/Http/Controllers/Api/OrderController.php
  - app/Http/Controllers/Api/ReviewController.php
  - app/Http/Controllers/Api/WishlistController.php
---

# Api

## Order lookup ignores auth — match by email/phone only
The `/api/orders/lookup` endpoint is used by guests who may have a stale Bearer token in localStorage from a previous login. Do NOT filter by `$request->user()->id` in the lookup query — always match by guest_email / guest_phone / shipping_phone. The `/api/orders` (list) and `/api/orders/{id}` (show) endpoints use user_id, but lookup is specifically for finding orders by contact info.

## Reviews moderation flow
Reviews use `is_approved` flag — admin must approve before they appear on storefront. API endpoint for listing uses `scopeApproved()`. Store review creation returns 201 with moderation notice, not immediate display.

## Wishlist toggle pattern
Wishlist uses toggle pattern via POST /wishlist/{slug}/toggle. Returns `{ wishlisted: true/false }`. Check endpoint accepts ?product_ids[]= for batch checking. All wishlist endpoints require auth:sanctum.
