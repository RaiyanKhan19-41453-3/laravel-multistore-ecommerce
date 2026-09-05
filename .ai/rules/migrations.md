---
paths:
  - 'database/migrations/**'
---

# Migrations

## Keep migrations MySQL-compatible
Migrations must run on both MySQL (dev, XAMPP MariaDB) and SQLite (tests use :memory:). Do not use: PRAGMA statements (use Schema::disable/enableForeignKeyConstraints), ->change() (needs doctrine/dbal on MySQL — use driver-aware DB::statement ALTER TABLE ... MODIFY for mysql/mariadb), auto-generated composite index names over 64 chars (pass explicit short name), or FKs referencing tables created later in the same timestamp batch (rename parent migration to an earlier timestamp). Wrap table-rebuild drops in disableForeignKeyConstraints (MySQL errno 1451).
