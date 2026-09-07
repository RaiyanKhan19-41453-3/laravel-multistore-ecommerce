---
paths:
  - bootstrap/app.php
---

# Bootstrap

## Pin auth-bridge middleware before AuthenticatesRequests
Laravel priority-sorts AuthenticatesRequests ahead of any unlisted middleware, so ResolveStoreToken (cookie-to-Bearer bridge) must be pinned via prependToPriorityList(AuthenticatesRequests::class, ResolveStoreToken::class). Without this, auth:sanctum runs before the bridge and cookie auth silently 401s. Symptom: cookie present in request but guard sees no token.
