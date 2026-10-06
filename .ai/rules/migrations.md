---
paths:
  - 'database/migrations/**'
---

# Migrations

## Keep migrations MySQL-compatible
Migrations must run on both MySQL (dev, XAMPP MariaDB) and SQLite (tests use :memory:). Do not use: PRAGMA statements (use Schema::disable/enableForeignKeyConstraints), ->change() (needs doctrine/dbal on MySQL — use driver-aware DB::statement ALTER TABLE ... MODIFY for mysql/mariadb), auto-generated composite index names over 64 chars (pass explicit short name), or FKs referencing tables created later in the same timestamp batch (rename parent migration to an earlier timestamp). Wrap table-rebuild drops in disableForeignKeyConstraints (MySQL errno 1451).

## Verify migrations on fresh MySQL, not just sqlite tests
sqlite feature tests migrate from scratch every run but cannot surface InnoDB rules (e.g. error 1553: cannot drop an index a FK depends on) or FK/index name drift. Before finalizing any migration change: run `php artisan migrate --force` against a scratch MySQL DB, then `migrate:rollback`, then migrate again, and diff the resulting index/FK names against dev. Editing a migration that dev already applied silently diverges the two schemas.

## Migration downs must replay history exactly
Full `migrate:rollback` (all migrations) must work on MySQL AND SQLite — verified per round with scratch DBs. Traps found: (1) a down() that recreates a table later older-downs touch must replicate the exact historical schema (index names included, e.g. composite `couriers_store_code_unique`, never a plain `code` unique); (2) table-rebuild migrations must re-create indexes older migrations created (e.g. `reviews_product_approved_idx`) in BOTH up() and down(), and pre-drop them first — SQLite index names are global across tables; (3) an index backing an FK cannot drop before the FK (errno 1553): drop-FK → drop-index → restore-FK; (4) `->constrained()` returns the FK definition, so `->change()` chained after it flags the FK — SQLite compiles the column as plain ADD → duplicate column; call `->change()` on the column def directly. Record schema truth with `SHOW INDEX`/`mysqldump --no-data` diffs (fresh vs round-tripped), not sqlite tests alone.
