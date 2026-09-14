---
paths:
  - 'app/Services/Zatca/**'
---

# Zatca

## ZATCA stays inert unless enabled
ZATCA code paths must be inert when disabled: config('zatca.enabled') gates tax computation, document queueing, and QR output so BD deployments see zero behavior change. Documentubin: build draft -> sign -> submit -> stamp, each step independently testable with Http::fake; never let invoicing exceptions break order confirmation (catch + warn).
