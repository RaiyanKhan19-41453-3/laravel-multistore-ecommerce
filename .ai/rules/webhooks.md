---
paths:
  - 'app/Http/Controllers/Api/Webhooks/**'
---

# Webhooks

## Webhooks resolve store-blind, then act as the order store
Webhook identity lookups (courier codes, tracking/consignment ids, gateway transaction ids) bypass the ambient store scope, then act in the order's store context (CurrentStore::set from the order) so inventory, discounts, and notifications resolve correctly. Webhook/callback routes are exempt from subscription enforcement.
