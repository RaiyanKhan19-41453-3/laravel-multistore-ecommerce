---
paths:
  - 'app/Jobs/**'
---

# App Jobs

## Serialize ZATCA submits per device, never dedupe by device
Serialize SubmitZatcaDocument per device with WithoutOverlapping middleware keyed by the document's device_serial. Never use ShouldBeUnique keyed by device here: it would silently drop distinct documents for the same device instead of queueing them.
