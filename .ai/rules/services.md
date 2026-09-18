---
paths:
  - 'app/Services/**'
  - app/Services/NotificationService.php
  - app/Services/OrderService.php
  - app/Services/InventoryService.php
  - app/Services/CatalogService.php
  - app/Services/ImageService.php
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
