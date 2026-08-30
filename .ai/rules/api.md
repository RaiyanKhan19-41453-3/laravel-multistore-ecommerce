---
paths:
  - app/Http/Controllers/Api/OrderController.php
---

# Api

## Order lookup ignores auth — match by email/phone only
The `/api/orders/lookup` endpoint is used by guests who may have a stale Bearer token in localStorage from a previous login. Do NOT filter by `$request->user()->id` in the lookup query — always match by guest_email / guest_phone / shipping_phone. The `/api/orders` (list) and `/api/orders/{id}` (show) endpoints use user_id, but lookup is specifically for finding orders by contact info.
