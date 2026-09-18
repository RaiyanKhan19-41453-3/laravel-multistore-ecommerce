---
paths:
  - 'app/Services/Zatca/**, app/Services/TaxService.php, app/Jobs/SubmitZatcaDocument.php'
---

# Jobs

## Per-store ZATCA seller and devices
Phase 10 ZATCA: seller identity merges global config with per-store settings keys zatca.seller.<field> (TaxService::sellerProfile/hasValidSellerProfile take storeId). Device resolution is deviceForStore(): store onboarded unit, then any store unit, then global device — ICV/PIH chains never cross stores. Documents carry the order's store explicitly. The submit job resolves its device from the document's own serial. Onboard with --store=slug to bind a device. Never call service methods on paginator results (unknown calls forward to the collection).
