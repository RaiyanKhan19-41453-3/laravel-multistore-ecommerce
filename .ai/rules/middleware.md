---
paths:
  - app/Http/Middleware/LogAdminActivity.php
  - app/Http/Middleware/ResolveAdminStore.php
---

# Middleware

## Admin audit logging
LogAdminActivity records non-GET admin requests (user, route name, method, path, IP, status, sanitized input) to audit_logs. Passwords/tokens/secrets/keys are redacted, values truncated. It never throws — auditing must not break the action. Viewable at admin/audit-logs (super-admin only).

## Admin middleware must manage CurrentStore lifecycle
ResolveAdminStore must:
1. Set CurrentStore when selected (header, session, or staff default membership lock)
2. Clear CurrentStore in platform-wide view (super-admin, no selection) so BelongsToStore scope doesn't filter admin listings
3. Implement terminate() to clear CurrentStore and AdminStoreContext after the response — prevents state leaking between requests in tests and Octane

The `selected()` method falls back to defaultMembershipId for staff, while `explicitlySelectedId()` does not. Use `selected()` to decide whether to set CurrentStore, not `explicitlySelectedId()`.
