---
paths:
  - 'app/Services/Couriers/**, app/Http/Controllers/Admin/CourierController.php, config/couriers.php'
---

# Couriers Http Controllers Admin

## Couriers are code-managed, not DB rows
The couriers table was dropped (migration 2026_10_02_000000): courier catalog, enable flags, API keys and webhook secrets live in config/couriers.php via env(). Admin Couriers page is read-only (index + test-connection only, route param is the code string). Shipments reference couriers by courier_code string plus a courier name snapshot; never re-add courier_id or Courier model.
