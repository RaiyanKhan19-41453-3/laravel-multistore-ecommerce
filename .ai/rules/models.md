---
paths:
  - 'app/Models/Shipping*.php'
---

# Models

## Shipping zone matching is case-insensitive
ShippingZone::containsCity() lowercases both the input and stored cities for case-insensitive matching. API accepts any case for city names.
