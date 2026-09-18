---
paths:
  - 'app/Services/SettingsService.php, database/migrations/*settings*, database/seeders/SettingsSeeder.php'
---

# Seeders

## Per-store settings with global fallback
Phase 4 settings: NULL store_id rows are platform global defaults, store rows override (merged read). Unique is (store_id, key). Service methods take optional storeId where null means "resolve current store" (never pass null to mean global — write globals via model/seeder). Writes forget that store's cache key; global writes flush all store keys. Existing rows were adopted by the default store. Tests must forget() the CurrentStore singleton after HTTP calls before asserting default-scope reads.
