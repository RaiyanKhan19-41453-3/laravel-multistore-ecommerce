---
paths:
  - 'app/Services/**'
  - app/Services/NotificationService.php
  - app/Services/OrderService.php
  - app/Services/InventoryService.php
  - app/Services/CatalogService.php
  - app/Services/ImageService.php
  - app/Services/SettingsService.php
  - app/Services/DiscountService.php
  - app/Services/PaymentService.php
---

# Services

## Avoid nullable service injection defaults
Laravel's container passes null (the default) for nullable service params like ?SettingsService $x = null instead of autowiring. Use a `= new Service` default (PHP 8.1+ new-in-initializers) or a required param so `new TaxService` in tests and container resolution both get a working instance.

## Notifications go through NotificationService
All customer notifications go through NotificationService (mail via queued Notification + afterCommit, SMS via SendOrderSms job). Channel toggles are settings keys notifications.mail_enabled / notifications.sms_enabled overriding config/notifications.php. SMS defaults off; the job no-ops when no gateway is configured so tests never hit real HTTP.

## Order notify-after-commit hooks
OrderService notifies after the transaction commits: createFromCart sends Placed (pending) or Confirmed (COD), confirmPayment sends Confirmed, updateStatus maps shipped/delivered only, cancel() sends Cancelled with reason. Never notify inside the transaction without afterCommit; never notify from expireOrder/handlePaymentFailure.

## Back-in-stock only on explicit restock
Back-in-stock mails fire only from adjust() and setQuantity() on a 0-to-available transition for active products. reserve()/release() are excluded — firing on every cancellation release would spam wishlisters.

## Catalog listing lives in CatalogService
Storefront listing logic lives in CatalogService::paginate (filters, sort, formatted items) and is shared by the JSON API and the Inertia store pages, so the response shape stays identical. Review aggregates use withCount/withAvg constrained to approved — never query reviews per product in a loop.

## Use withoutGlobalScope for cross-store lookups
When a service needs to read a model's store_id regardless of the current store context (e.g., ImageService reading Product.store_id for file paths), use `Model::withoutGlobalScope(BelongsToStore::class)->whereKey(...)` to bypass the scope. The global scope would otherwise filter out the record if CurrentStore differs from the model's store.

## Fulfillment status moves use markShipped/markDelivered
Fulfillment moves go through markShipped/markDelivered (machine-checked, idempotent, with notifications), never direct status writes. Allowed from confirmed/processing/shipped only; pending, cancelled, expired, completed throw. Admin shipment updates and both courier webhooks use them and convert InvalidArgumentException to errors/logs instead of 500s.

## Settings reads bypass ambient store scope
SettingsService reads bypass the BelongsToStore scope (explicit store wins over ambient context) so NULL-store globals stay visible on every storefront request. Cache keys are versioned (v2); bump the version if the merge logic changes again since entries are rememberForever.

## Mixed discount targets are a union
Mixed product+variant discount targets are a UNION: a line qualifies through either side, in both getEligibleSubtotal and getPerItemDiscountAmounts (items path). getTargeting still reports product-first for display only; never use its masked variant_ids to decide eligibility.

## tran_id webhook fallback is sslcommerz-only
The tran_id-as-payment-id webhook fallback is sslcommerz-only (its gateway id IS our payment id). For every other gateway tran_id is untrusted input and must not resolve payments, especially since failed/cancelled statuses skip server verification.
