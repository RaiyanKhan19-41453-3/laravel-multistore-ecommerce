---
paths:
  - '**'
---

# General

## Keep destructive checks off the dev database
Never run destructive writes (tinker deletes, db:seed, migrate:fresh/rollback) against the dev database. Verify migrations on a scratch DB (CREATE DATABASE audit_tmp + DB_DATABASE=audit_tmp) and drop it afterwards; verify seeders by reading code, not by running them on dev.

## No commits or pushes without explicit request
Never run git commit, push, amend, or create PRs unless the user explicitly asks for it in that turn. Staging without committing is fine when preparing, but always stop before commit/push without explicit approval.

## No em dashes anywhere
Never use the em dash character (U+2014) anywhere: not in UI copy, i18n strings, invoice labels, notification subjects, code comments, seeders, or tests. Use a colon, comma, hyphen, or plain rewording instead. Empty-value placeholders use '-'.
