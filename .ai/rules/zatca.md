---
paths:
  - 'app/Services/Zatca/**'
  - app/Services/Zatca/UblInvoiceBuilder.php
---

# Zatca

## ZATCA stays inert unless enabled
ZATCA code paths must be inert when disabled: config('zatca.enabled') gates tax computation, document queueing, and QR output so BD deployments see zero behavior change. Documentubin: build draft -> sign -> submit -> stamp, each step independently testable with Http::fake; never let invoicing exceptions break order confirmation (catch + warn).

## Split taxable and exempt ZATCA subtotals
Mixed invoices emit one TaxSubtotal per category: S over the taxable base, E (0%) over the exempt base, and matching S/E line codes. Pure-taxable output keeps the single-S shape. Never lump exempt lines into the S taxable amount.
