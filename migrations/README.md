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
