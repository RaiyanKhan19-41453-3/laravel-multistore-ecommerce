---
paths:
  - '**'
---

# General

## Keep destructive checks off the dev database
Never run destructive writes (tinker deletes, db:seed, migrate:fresh/rollback) against the dev database. Verify migrations on a scratch DB (CREATE DATABASE audit_tmp + DB_DATABASE=audit_tmp) and drop it afterwards; verify seeders by reading code, not by running them on dev.
