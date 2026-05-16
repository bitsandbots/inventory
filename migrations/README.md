# Migrations

SQL migrations for schema changes that ship after the initial `schema.sql`.

## Convention

Each migration is a pair of files:

- `NNN_<slug>.up.sql` — forward migration
- `NNN_<slug>.down.sql` — reverse migration (must restore the prior state)

Numbering is sequential and never reused.

## Running

Migrations are intentionally **not** auto-applied by `install.sh`. They require deliberate review and a backup before running:

```bash
# Always back up first
sudo mysqldump --single-transaction inventory > inventory-pre-NNN.sql

# Inspect the migration
less migrations/001_quantity_int.up.sql

# Apply
sudo mysql inventory < migrations/001_quantity_int.up.sql

# Verify
sudo mysql inventory -e "DESCRIBE products; DESCRIBE stock;"
```

## Rolling back

```bash
sudo mysql inventory < migrations/001_quantity_int.down.sql
```

## Index

| # | Name | Forward | Reverse | Status |
|---|------|---------|---------|--------|
| 001 | `quantity_int` | Convert `products.quantity` and `stock.quantity` from VARCHAR(50) to INT | Restore to VARCHAR(50) | Applied 2026-05-14 |
| 002 | `failed_logins` | Create `failed_logins` rate-limit table | Drop table | Applied 2026-05-14 |
| 003 | `log_user_fk` | FK `log.user_id → users.id` ON DELETE SET NULL | Drop FK, revert column to signed | Applied 2026-05-15 |
| 004 | `settings_table` | Create `settings` table, seed `currency_code='USD'` | Drop `settings` table | New — see PR for currency feature |
| 005 | `users_soft_delete` | Add `deleted_at TIMESTAMP NULL` and `deleted_by INT` to `users` | Drop columns | Soft-delete feature (005–009) |
| 006 | `customers_soft_delete` | Add `deleted_at TIMESTAMP NULL` and `deleted_by INT` to `customers` | Drop columns | Soft-delete feature (005–009) |
| 007 | `sales_soft_delete` | Add `deleted_at TIMESTAMP NULL` and `deleted_by INT` to `sales` | Drop columns | Soft-delete feature (005–009) |
| 008 | `orders_soft_delete` | Add `deleted_at TIMESTAMP NULL` and `deleted_by INT` to `orders` | Drop columns | Soft-delete feature (005–009) |
| 009 | `stock_soft_delete` | Add `deleted_at TIMESTAMP NULL` and `deleted_by INT` to `stock` | Drop columns | Soft-delete feature (005–009) |
| 010 | `orgs_table` | Create `orgs` table (id, name, slug, created_at, deleted_at, deleted_by) | Drop table | Tenancy feature (010–021) |
| 011 | `org_members_table` | Create `org_members` join table (org_id, user_id, role, joined_at) | Drop table | Tenancy feature (010–021) |
| 012 | `default_org_seed` | Insert Default Organization (id=1) + backfill all existing users into org 1 via user_level→role mapping | Delete seed rows | Tenancy feature (010–021) |
| 013 | `customers_org_id` | Add `org_id NOT NULL DEFAULT 1` to `customers`; reshape UNIQUE(name) → UNIQUE(org_id, name); add composite index | Drop column, restore UNIQUE | Tenancy feature (010–021) |
| 014 | `products_org_id` | Same pattern as 013 for `products` | Drop column, restore UNIQUE | Tenancy feature (010–021) |
| 015 | `categories_org_id` | Same pattern as 013 for `categories` | Drop column, restore UNIQUE | Tenancy feature (010–021) |
| 016 | `sales_org_id` | Add `org_id NOT NULL DEFAULT 1` + composite index `(org_id, deleted_at)` to `sales` | Drop column | Tenancy feature (010–021) |
| 017 | `orders_org_id` | Same pattern as 016 for `orders` | Drop column | Tenancy feature (010–021) |
| 018 | `stock_org_id` | Same pattern as 016 for `stock` | Drop column | Tenancy feature (010–021) |
| 019 | `media_org_id` | Add `org_id NOT NULL DEFAULT 1` + index to `media` | Drop column | Tenancy feature (010–021) |
| 020 | `settings_org_id` | Drop PK(setting_key); add `org_id NOT NULL DEFAULT 1`; recreate PK(org_id, setting_key); FK to orgs | Restore old PK, drop org_id | Tenancy feature (010–021) |
| 021 | `users_last_active_org` | Add nullable `last_active_org_id` FK to `users.last_active_org_id → orgs(id) ON DELETE SET NULL` | Drop column | Tenancy feature (010–021) |
