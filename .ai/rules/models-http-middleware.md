---
paths:
  - 'app/Models/Plan.php, app/Models/Subscription.php, app/Models/Store.php, app/Http/Middleware/EnsureStoreSubscription.php'
---

# Models Http Middleware

## Platform billing and enforcement
Phase 9 billing: plans (global) + one subscription row per store (store_id unique). Trial derives from store.created_at + platform.billing.trial_days when no row exists. Store::billingStatus()/isBillingEntitled() never throw (fail-open to active). EnsureStoreSubscription is inert unless platform.billing.enforced=true; super-admins bypass; auth/billing/signup routes exempt. Enable enforcement only after assigning active subscriptions — old stores' trials count from creation. Merchant self-subscribe records pending for platform activation (no gateway yet).
