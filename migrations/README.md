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
| 001 | `quantity_int` | Convert `products.quantity` and `stock.quantity` from VARCHAR(50) to INT | Restore to VARCHAR(50) | Pending — see gap-analysis.md |
