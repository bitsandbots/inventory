# Soft-Delete Pattern — Design

- **Date:** 2026-05-16
- **Status:** Draft, awaiting user review
- **Scope:** First of three sub-projects (soft-delete → tenancy/per-org currency → Playwright UI tests)
- **Source memo:** `next_steps_inventory.md` § "Deferred work" item 1

## 1. Goals

Replace hard-delete with a recoverable soft-delete on audit-heavy tables. Eliminate the existing risk that an admin click in the UI permanently destroys data with no DB-side recovery path. Preserve historical reporting integrity even when parent records (users, customers) are removed from active listings.

## 2. Scope

### In scope

Five tables get soft-delete: `users`, `customers`, `sales`, `orders`, `stock`.

These are the audit-heavy / hard-to-reconstruct tables. Existing callers of `delete_by_id()` on these tables get re-routed to a new `soft_delete_by_id()`.

### Out of scope (deliberate)

- `products`, `categories` — catalog data; re-adding a deleted SKU is cheap.
- `media` — rows are tied to physical files on disk; soft-delete leaves orphan files.
- `log`, `failed_logins` — these are themselves the audit log; rows do not represent business state.
- `user_groups`, `settings` — rarely deleted; admin-only.
- Bulk-restore / bulk-purge UI.
- Retention policy (auto-purge after N days).
- Any tenancy concerns — that is sub-project #2.

## 3. Architecture

### 3.1 Schema

Each in-scope table gets two columns:

```sql
ALTER TABLE `<table>`
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER <last_existing_col>,
  ADD COLUMN `deleted_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
  ADD KEY `idx_<table>_deleted_at` (`deleted_at`),
  ADD CONSTRAINT `fk_<table>_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;
```

The `ON DELETE SET NULL` mirrors PR #27's `fk_log_user` pattern: a later removal of the actor user does not corrupt the audit trail in other tables.

### 3.2 Behavior summary

- **Soft-delete** sets `deleted_at = NOW()` and `deleted_by = current user id`.
- **Restore** sets both back to `NULL`.
- **Purge** runs the real `DELETE FROM <table> WHERE id = ?`. Purge refuses to run on a row that has not been soft-deleted first.
- **Reads default-filter** to `deleted_at IS NULL` on all 5 tables.
- **Cascade is row-local**: a soft-deleted customer's existing sales stay visible in the sales list; lookups across tables use a `_with_deleted` variant so old sale rows can still render the deleted customer's name (with a `(deleted)` badge in the UI).

## 4. Code Changes

### 4.1 `includes/sql.php`

New functions (added near existing `delete_by_id` at line 105):

```php
function soft_delete_by_id(string $table, int $id, ?int $actor_user_id = null): bool;
function restore_by_id(string $table, int $id): bool;
function purge_by_id(string $table, int $id): bool;
function find_with_deleted(string $table): array;
function find_by_id_with_deleted(string $table, int $id): ?array;
function table_has_soft_delete(string $table): bool;
```

`soft_delete_by_id()` reads the actor id from `$session->user_id` when `$actor_user_id` is null. CSRF and permission checks remain in the calling page (existing pattern).

`table_has_soft_delete()` caches its answer in a module-level static array, populated lazily on first call per table. This keeps the auto-filter's overhead near zero. If the column is absent (e.g., during the deploy → migrate window) the function returns `false` and the generic helper does not append the filter — the system serves correct, unfiltered reads instead of 500-ing.

Modified generics (lines 21 and 59):

- `find_all($table)` — appends `WHERE deleted_at IS NULL` when `table_has_soft_delete($table)` is true.
- `find_by_id($table, $id)` — same.
- `find_by_sql($sql)` — **untouched**. Raw-SQL escape hatch.

Hand-edited raw-SQL helpers (each adds `AND t.deleted_at IS NULL` to the WHERE):

- `find_all_user()` (line 295)
- `find_all_sales()` (line 759)
- `find_all_orders()` (line 779)
- `find_all_customer_info_by_name()` (line 576)
- Any other `find_*` whose primary FROM is one of the 5 in-scope tables — full grep audit during the implementation plan.

### 4.2 Delete-page callers

Five pages route through `soft_delete_by_id`:

- `users/delete_user.php:15` → `soft_delete_by_id('users', ...)`
- `customers/delete_customer.php:23` → `soft_delete_by_id('customers', ...)`
- `sales/delete_sale.php:32` → `soft_delete_by_id('sales', ...)`
- `sales/delete_order.php:26,31` → `soft_delete_by_id('sales', ...)` and `soft_delete_by_id('orders', ...)`
- `products/delete_stock.php:26` → `soft_delete_by_id('stock', ...)`

The four out-of-scope callers (`media`, `user_groups`, `log`, `categories`, `products`) stay on hard-delete.

### 4.3 Trash UI

**`users/trash.php`** — admin-only, `page_require_level(1)`. Query string `?table=users|customers|sales|orders|stock` (default `users`). Invalid table → redirect with flash error.

Layout:

- Reuses `layouts/header.php`, `layouts/admin_menu.php`, `layouts/footer.php`.
- Five tabs across the top; active tab corresponds to `?table=`.
- Per-table HTML table. Common columns: id, `deleted_at`, `deleted_by` (resolved to username via `find_by_id_with_deleted('users', ...)` so a soft-deleted admin still renders). Per-table label columns:
  - `users` — `username`, `name`
  - `customers` — `name`
  - `sales` — `date`, `qty`, `price`
  - `orders` — `customer`, `date`
  - `stock` — `product_id` (resolved to product title via `find_by_id('products', ...)`), `quantity`, `date`
- Per-row actions: **Restore** and **Purge**, both CSRF-protected.
- Empty state: "No soft-deleted &lt;table&gt; rows."

**`users/restore.php`** — POST, verify_csrf, page_require_level(1), table allowlist, int id, call `restore_by_id`, flash, redirect.

**`users/purge.php`** — same shape, calls `purge_by_id`. Refuses purge of an active row.

**Menu integration:** new link under User Management → **Trash** in `layouts/admin_menu.php`, gated identically to Settings.

**Output escaping:** every dynamic value passes through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. The `table` parameter is whitelist-validated.

## 5. Testing

New suite: `tests/SoftDeleteTest.php`. Wired into `tests/run.sh` and `tests/bootstrap.php`. Follows existing CRUD test patterns; uses real DB; all rows prefixed `HARNESS_`; teardown purges (not soft-deletes) every HARNESS_ row.

Cases:

1. Schema present on all 5 tables; skip suite cleanly if migrations 005–009 are not applied (mirrors `SettingsTest`).
2. `soft_delete_by_id` hides row from `find_all`.
3. Hides from raw-SQL helper for each table.
4. `find_with_deleted` still returns it.
5. `deleted_by` recorded correctly.
6. `restore_by_id` un-hides; both columns return to NULL.
7. `purge_by_id` removes permanently.
8. Purge refuses an active row.
9. Row-local cascade: soft-deleted customer's existing sale still appears in `find_all_sales()`.
10. Per-table coverage loop confirms each table's wiring + raw-SQL helper edit.

Existing suites (`AuthTest`, `CRUDTest`, `CSRFTest`, `SecurityHeadersTest`, `SettingsTest`) must stay green. CRUDTest's hard-delete assertions, if any, switch to `purge_by_id`.

## 6. Migrations

One migration per table; users first because other tables' `deleted_by` FK references it.

- `migrations/005_users_soft_delete.{up,down}.sql`
- `migrations/006_customers_soft_delete.{up,down}.sql`
- `migrations/007_sales_soft_delete.{up,down}.sql`
- `migrations/008_orders_soft_delete.{up,down}.sql`
- `migrations/009_stock_soft_delete.{up,down}.sql`
- `schema.sql` — mirror all five columns + indexes + FKs.
- `migrations/README.md` — index rows for 005–009.

Each `.down.sql` is the literal inverse (drop FK → column → index). Validated by applying up→down→up on a scratch DB before merge.

## 7. Build Sequence

Branch `feature/soft-delete-pattern`. One PR.

1. Add `tests/SoftDeleteTest.php` against current (pre-migration) schema; assert it skips cleanly. TDD anchor.
2. Migrations 005–009 + `schema.sql` mirror + README index. Apply on dev; existing suites stay green.
3. `includes/sql.php` — new helpers + `table_has_soft_delete()` + generic auto-filter. SoftDeleteTest cases 1–8 should pass.
4. `includes/sql.php` — hand-edit raw-SQL helpers (after grep audit). Cases 2, 3, 9 now pass per table.
5. Route the 5 delete pages through `soft_delete_by_id`. Manual smoke each.
6. Trash UI: `users/trash.php`, `users/restore.php`, `users/purge.php`, admin menu link. Manual smoke per table.
7. Full suite green → PR.

## 8. Deploy

```bash
sudo mysqldump --single-transaction inventory > inventory-pre-005-009.sql
sudo mysql inventory < migrations/005_users_soft_delete.up.sql
sudo mysql inventory < migrations/006_customers_soft_delete.up.sql
sudo mysql inventory < migrations/007_sales_soft_delete.up.sql
sudo mysql inventory < migrations/008_orders_soft_delete.up.sql
sudo mysql inventory < migrations/009_stock_soft_delete.up.sql
```

`table_has_soft_delete()` falls back to `false` when the column is not yet present, so traffic between the code-deploy and the migration-apply continues to render correctly — same defensive pattern PR #30 used for the settings table.

**Rollback:** matching `.down.sql` files reverse each migration. Worst case, restore from the `inventory-pre-005-009.sql` dump.

## 9. Open questions

None. All decisions captured above.
