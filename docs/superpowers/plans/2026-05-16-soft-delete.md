# Soft-Delete Pattern Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace hard-delete with recoverable soft-delete on 5 audit-heavy tables (users, customers, sales, orders, stock), with an admin trash UI for restore and purge.

**Architecture:** Each in-scope table gets `deleted_at TIMESTAMP NULL` + `deleted_by INT NULL` (FK→users.id ON DELETE SET NULL). `includes/sql.php` generics auto-filter `WHERE deleted_at IS NULL` via cached schema introspection; raw-SQL helpers are hand-edited per the in-scope table list. New `users/trash.php` admin UI lists soft-deleted rows with Restore/Purge actions. Cascade is row-local — historical reports stay intact even when a parent row is deleted.

**Tech Stack:** PHP 8.x, MySQL/MariaDB, vanilla mysqli (via the project's `$db` wrapper), no framework. Tests are PHP CLI scripts run through `tests/run.sh`.

**Spec:** `docs/superpowers/specs/2026-05-16-soft-delete-design.md`

**Branch:** `feature/soft-delete-pattern`

---

## Task 1: Create branch and harness

**Files:**
- None — git only.

- [ ] **Step 1: Create the feature branch**

```bash
git checkout -b feature/soft-delete-pattern
git status
```

Expected: `On branch feature/soft-delete-pattern` with clean working tree (the `inventory-pre-004-Sat` file is gitignored).

- [ ] **Step 2: Confirm baseline tests are green**

```bash
bash tests/run.sh
```

Expected: All 5 suites pass (AuthTest, CRUDTest, CSRFTest, SecurityHeadersTest, SettingsTest). If SecurityHeadersTest fails, ensure Apache is running on `:8080`; if SettingsTest fails, ensure migration 004 is applied locally.

---

## Task 2: TDD anchor — write the SoftDeleteTest skip-when-missing case

The first test runs against the *current* (pre-migration) schema and must skip cleanly. This locks in the skip pattern and gives the rest of the plan a safety net.

**Files:**
- Create: `tests/SoftDeleteTest.php`
- Modify: `tests/run.sh` (one new `run_test` line)

- [ ] **Step 1: Create the test file with bootstrap + skip logic**

```php
<?php
/**
 * tests/SoftDeleteTest.php
 *
 * Integration tests for the soft-delete pattern (migrations 005-009).
 * Skips cleanly when migrations are not yet applied locally — mirrors
 * the SettingsTest skip pattern.
 *
 * All test rows use the HARNESS_ prefix; teardown purges them.
 */

require_once __DIR__ . '/bootstrap.php';

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void
{
    global $pass, $fail;
    try {
        $fn();
        $pass++;
        echo "  PASS: $name\n";
    } catch (Throwable $e) {
        $fail++;
        echo "  FAIL: $name — " . $e->getMessage() . "\n";
    }
}

function check(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

echo "=== SoftDeleteTest ===\n\n";

// Skip gracefully if migrations 005-009 are not yet applied locally.
// Probe one table's deleted_at column — if missing, the whole suite skips.
$soft_delete_ready = false;
try {
    global $db;
    $r = $db->connection()->query("SHOW COLUMNS FROM `users` LIKE 'deleted_at'");
    $soft_delete_ready = ($r !== false && $r->num_rows > 0);
    if ($r) {
        $r->free();
    }
} catch (\Throwable $e) {
    $soft_delete_ready = false;
}
if (!$soft_delete_ready) {
    echo "  SKIPPED: `users.deleted_at` column not present.\n";
    echo "  Apply migrations 005-009 to enable these tests:\n";
    echo "    for n in 005 006 007 008 009; do\n";
    echo "      sudo mysql inventory < migrations/\${n}_*.up.sql\n";
    echo "    done\n";
    echo "\n---\nResults: 0 passed, 0 failed (suite skipped)\n";
    exit(0);
}

// Test cases are added in later tasks.

echo "\n---\nResults: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
```

- [ ] **Step 2: Wire the new suite into `tests/run.sh`**

Open `tests/run.sh`, find the existing `run_test` line for `SettingsTest.php`. Add the new line immediately after it.

The existing line looks like:
```bash
run_test "tests/SettingsTest.php" "Settings & Currency (integration)"
```

Add directly below:
```bash
run_test "tests/SoftDeleteTest.php" "Soft-Delete Pattern (integration)"
```

- [ ] **Step 3: Run the new suite and confirm clean skip**

```bash
php tests/SoftDeleteTest.php
```

Expected output:
```
=== SoftDeleteTest ===

  SKIPPED: `users.deleted_at` column not present.
  Apply migrations 005-009 to enable these tests:
    for n in 005 006 007 008 009; do
      sudo mysql inventory < migrations/${n}_*.up.sql
    done

---
Results: 0 passed, 0 failed (suite skipped)
```
Exit code: `0`.

- [ ] **Step 4: Run the full suite to confirm nothing else broke**

```bash
bash tests/run.sh
```

Expected: SoftDeleteTest shows as "SKIPPED" but the runner counts it as PASSED (exit 0). All other suites stay green.

- [ ] **Step 5: Commit**

```bash
git add tests/SoftDeleteTest.php tests/run.sh
git commit -m "test(soft-delete): scaffold SoftDeleteTest suite with skip-when-missing"
```

---

## Task 3: Migration 005 — users.deleted_at + deleted_by

The users table goes first because every other migration's `deleted_by` FK references `users(id)`.

**Files:**
- Create: `migrations/005_users_soft_delete.up.sql`
- Create: `migrations/005_users_soft_delete.down.sql`

- [ ] **Step 1: Write the up migration**

```sql
-- Migration 005 — users soft-delete columns.
--
-- Adds deleted_at + deleted_by to users so admins can soft-delete and
-- restore users from the trash UI. deleted_by references users(id) with
-- ON DELETE SET NULL so a later removal of the actor user does not
-- corrupt the audit trail (mirrors fk_log_user from migration 003).
--
-- Reverse: see 005_users_soft_delete.down.sql

SELECT 'Adding deleted_at + deleted_by to users...' AS step;

ALTER TABLE `users`
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_login`,
  ADD COLUMN `deleted_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
  ADD KEY `idx_users_deleted_at` (`deleted_at`),
  ADD CONSTRAINT `fk_users_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

SELECT 'After migration:' AS step;
SHOW COLUMNS FROM `users` LIKE 'deleted_%';
```

- [ ] **Step 2: Write the down migration**

```sql
-- Reverse of 005_users_soft_delete.up.sql.
-- Drops FK first, then key, then columns.

SELECT 'Dropping deleted_at + deleted_by from users...' AS step;

ALTER TABLE `users`
  DROP FOREIGN KEY `fk_users_deleted_by`,
  DROP KEY `idx_users_deleted_at`,
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`;

SELECT 'After reverse:' AS step;
SHOW COLUMNS FROM `users` LIKE 'deleted_%';
```

- [ ] **Step 3: Apply on dev, then revert, then apply again (up-down-up cycle)**

```bash
sudo mysqldump --single-transaction inventory > inventory-pre-005-009.sql
sudo mysql inventory < migrations/005_users_soft_delete.up.sql
sudo mysql inventory -e "SHOW COLUMNS FROM users LIKE 'deleted_%';"
sudo mysql inventory < migrations/005_users_soft_delete.down.sql
sudo mysql inventory -e "SHOW COLUMNS FROM users LIKE 'deleted_%';"
sudo mysql inventory < migrations/005_users_soft_delete.up.sql
```

Expected: first SHOW lists both columns; second SHOW returns no rows; third application succeeds without error.

- [ ] **Step 4: Verify existing tests still pass**

```bash
bash tests/run.sh
```

Expected: All 5 baseline suites green; SoftDeleteTest still skipped (column probe in step 1 of Task 2 was correct, but the rest of the suite has no cases yet).

- [ ] **Step 5: Commit**

```bash
git add migrations/005_users_soft_delete.up.sql migrations/005_users_soft_delete.down.sql
git commit -m "feat(migrations): 005 add deleted_at + deleted_by to users"
```

---

## Task 4: Migrations 006-009 — customers, sales, orders, stock

Repeat Task 3's shape for the remaining four tables. Each migration is mechanical: the only changes are the table name, the `AFTER <last_existing_col>` clause, and the constraint/key names. None of these tables self-FK like users does.

**Files:**
- Create: `migrations/006_customers_soft_delete.up.sql` + `.down.sql`
- Create: `migrations/007_sales_soft_delete.up.sql` + `.down.sql`
- Create: `migrations/008_orders_soft_delete.up.sql` + `.down.sql`
- Create: `migrations/009_stock_soft_delete.up.sql` + `.down.sql`

**Per-table parameters** (insert into the Task 3 templates):

| Migration | Table | AFTER column |
|---|---|---|
| 006 | `customers` | `paymethod` |
| 007 | `sales` | `date` |
| 008 | `orders` | `date` |
| 009 | `stock` | `date` |

- [ ] **Step 1: Write `migrations/006_customers_soft_delete.up.sql`**

```sql
-- Migration 006 — customers soft-delete columns.
-- See 005_users_soft_delete.up.sql for rationale.

SELECT 'Adding deleted_at + deleted_by to customers...' AS step;

ALTER TABLE `customers`
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `paymethod`,
  ADD COLUMN `deleted_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
  ADD KEY `idx_customers_deleted_at` (`deleted_at`),
  ADD CONSTRAINT `fk_customers_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

SELECT 'After migration:' AS step;
SHOW COLUMNS FROM `customers` LIKE 'deleted_%';
```

- [ ] **Step 2: Write `migrations/006_customers_soft_delete.down.sql`**

```sql
SELECT 'Dropping deleted_at + deleted_by from customers...' AS step;

ALTER TABLE `customers`
  DROP FOREIGN KEY `fk_customers_deleted_by`,
  DROP KEY `idx_customers_deleted_at`,
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`;

SELECT 'After reverse:' AS step;
SHOW COLUMNS FROM `customers` LIKE 'deleted_%';
```

- [ ] **Step 3: Write `migrations/007_sales_soft_delete.up.sql`**

```sql
SELECT 'Adding deleted_at + deleted_by to sales...' AS step;

ALTER TABLE `sales`
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `date`,
  ADD COLUMN `deleted_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
  ADD KEY `idx_sales_deleted_at` (`deleted_at`),
  ADD CONSTRAINT `fk_sales_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

SELECT 'After migration:' AS step;
SHOW COLUMNS FROM `sales` LIKE 'deleted_%';
```

- [ ] **Step 4: Write `migrations/007_sales_soft_delete.down.sql`**

```sql
SELECT 'Dropping deleted_at + deleted_by from sales...' AS step;

ALTER TABLE `sales`
  DROP FOREIGN KEY `fk_sales_deleted_by`,
  DROP KEY `idx_sales_deleted_at`,
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`;

SELECT 'After reverse:' AS step;
SHOW COLUMNS FROM `sales` LIKE 'deleted_%';
```

- [ ] **Step 5: Write `migrations/008_orders_soft_delete.up.sql`**

```sql
SELECT 'Adding deleted_at + deleted_by to orders...' AS step;

ALTER TABLE `orders`
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `date`,
  ADD COLUMN `deleted_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
  ADD KEY `idx_orders_deleted_at` (`deleted_at`),
  ADD CONSTRAINT `fk_orders_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

SELECT 'After migration:' AS step;
SHOW COLUMNS FROM `orders` LIKE 'deleted_%';
```

- [ ] **Step 6: Write `migrations/008_orders_soft_delete.down.sql`**

```sql
SELECT 'Dropping deleted_at + deleted_by from orders...' AS step;

ALTER TABLE `orders`
  DROP FOREIGN KEY `fk_orders_deleted_by`,
  DROP KEY `idx_orders_deleted_at`,
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`;

SELECT 'After reverse:' AS step;
SHOW COLUMNS FROM `orders` LIKE 'deleted_%';
```

- [ ] **Step 7: Write `migrations/009_stock_soft_delete.up.sql`**

```sql
SELECT 'Adding deleted_at + deleted_by to stock...' AS step;

ALTER TABLE `stock`
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `date`,
  ADD COLUMN `deleted_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
  ADD KEY `idx_stock_deleted_at` (`deleted_at`),
  ADD CONSTRAINT `fk_stock_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

SELECT 'After migration:' AS step;
SHOW COLUMNS FROM `stock` LIKE 'deleted_%';
```

- [ ] **Step 8: Write `migrations/009_stock_soft_delete.down.sql`**

```sql
SELECT 'Dropping deleted_at + deleted_by from stock...' AS step;

ALTER TABLE `stock`
  DROP FOREIGN KEY `fk_stock_deleted_by`,
  DROP KEY `idx_stock_deleted_at`,
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`;

SELECT 'After reverse:' AS step;
SHOW COLUMNS FROM `stock` LIKE 'deleted_%';
```

- [ ] **Step 9: Apply all four migrations on dev, then up-down-up the lot**

```bash
sudo mysql inventory < migrations/006_customers_soft_delete.up.sql
sudo mysql inventory < migrations/007_sales_soft_delete.up.sql
sudo mysql inventory < migrations/008_orders_soft_delete.up.sql
sudo mysql inventory < migrations/009_stock_soft_delete.up.sql
sudo mysql inventory -e "SHOW COLUMNS FROM customers LIKE 'deleted_%'; SHOW COLUMNS FROM sales LIKE 'deleted_%'; SHOW COLUMNS FROM orders LIKE 'deleted_%'; SHOW COLUMNS FROM stock LIKE 'deleted_%';"
```

Expected: all four tables show both columns (4 pairs of rows in the combined SHOW output).

- [ ] **Step 10: Run baseline tests**

```bash
bash tests/run.sh
```

Expected: all 5 baseline suites green. SoftDeleteTest is still empty so it just prints the header and "0 passed, 0 failed" — that's fine.

- [ ] **Step 11: Commit**

```bash
git add migrations/006_*.sql migrations/007_*.sql migrations/008_*.sql migrations/009_*.sql
git commit -m "feat(migrations): 006-009 add deleted_at + deleted_by to customers/sales/orders/stock"
```

---

## Task 5: Mirror columns into `schema.sql`

Fresh installs and CI pull from `schema.sql` directly — it must mirror the migrations or CI will start in a broken state.

**Files:**
- Modify: `schema.sql`

- [ ] **Step 1: Add the two columns + index to each of the 5 `CREATE TABLE` statements**

For each in-scope table, insert two new column lines inside the parens (after the last existing column line) and a new KEY line if MySQL dump format already uses an indexes block — match the existing dump style. Specifically:

- `users` — insert after the `last_login` column line:
  ```
    `deleted_at` timestamp NULL DEFAULT NULL,
    `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  ```
- `customers` — insert after the `paymethod` line: same two columns.
- `sales` — insert after the `date` line: same two columns.
- `orders` — insert after the `date` line: same two columns.
- `stock` — insert after the `date` line: same two columns.

- [ ] **Step 2: Add the matching KEY definitions in each table's index block**

`schema.sql` keeps indexes in `ALTER TABLE ... ADD ... KEY` form near the bottom (around lines 280–400). Add one new line per table just before that table's existing index block:

```sql
ALTER TABLE `users` ADD KEY `idx_users_deleted_at` (`deleted_at`);
ALTER TABLE `customers` ADD KEY `idx_customers_deleted_at` (`deleted_at`);
ALTER TABLE `sales` ADD KEY `idx_sales_deleted_at` (`deleted_at`);
ALTER TABLE `orders` ADD KEY `idx_orders_deleted_at` (`deleted_at`);
ALTER TABLE `stock` ADD KEY `idx_stock_deleted_at` (`deleted_at`);
```

- [ ] **Step 3: Add the matching FK constraints near the existing FK block**

Existing FK block lives around line 377 (where `FK_products`, `SK`, `FK_user`, `fk_log_user` live). Add five new constraints below them:

```sql
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `stock`
  ADD CONSTRAINT `fk_stock_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
```

- [ ] **Step 4: Verify schema.sql imports cleanly into a scratch DB**

```bash
sudo mysql -e "CREATE DATABASE inventory_schema_check; "
sudo mysql inventory_schema_check < schema.sql
sudo mysql inventory_schema_check -e "SHOW COLUMNS FROM users LIKE 'deleted_%'; SHOW COLUMNS FROM customers LIKE 'deleted_%'; SHOW COLUMNS FROM sales LIKE 'deleted_%'; SHOW COLUMNS FROM orders LIKE 'deleted_%'; SHOW COLUMNS FROM stock LIKE 'deleted_%';"
sudo mysql -e "DROP DATABASE inventory_schema_check;"
```

Expected: 10 columns shown (2 × 5 tables), no errors.

- [ ] **Step 5: Commit**

```bash
git add schema.sql
git commit -m "feat(schema): mirror 005-009 soft-delete columns into schema.sql"
```

---

## Task 6: Update `migrations/README.md` index

**Files:**
- Modify: `migrations/README.md`

- [ ] **Step 1: Append index rows for 005-009**

Find the table of migrations in `migrations/README.md` and add five new rows below the existing 004 row. Match the existing column shape (Migration | Description | Applied to live? | Notes). Sample row:

```markdown
| 005 | users: add deleted_at + deleted_by | NO | Soft-delete feature, paired 005-009 |
| 006 | customers: add deleted_at + deleted_by | NO | Soft-delete feature, paired 005-009 |
| 007 | sales: add deleted_at + deleted_by | NO | Soft-delete feature, paired 005-009 |
| 008 | orders: add deleted_at + deleted_by | NO | Soft-delete feature, paired 005-009 |
| 009 | stock: add deleted_at + deleted_by | NO | Soft-delete feature, paired 005-009 |
```

- [ ] **Step 2: Commit**

```bash
git add migrations/README.md
git commit -m "docs(migrations): index rows for 005-009"
```

---

## Task 7: Add `table_has_soft_delete()` introspection helper to `includes/sql.php`

This is the keystone helper. Generics, raw-SQL helpers, and the trash UI all depend on it. It must:
- Return `true` only when the table is in the in-scope allowlist AND its `deleted_at` column exists.
- Cache results in a module-level static.
- Never throw — return `false` on any unexpected error so the deploy-window fallback works.

**Files:**
- Modify: `includes/sql.php` (add after `delete_by_id` at line 117)

- [ ] **Step 1: Write the failing test**

Append to `tests/SoftDeleteTest.php`, immediately above the closing `echo "\n---\n..."` line:

```php
// Task 7 — table_has_soft_delete introspection.
test('table_has_soft_delete returns true for in-scope tables', function () {
    check(table_has_soft_delete('users') === true, 'users should be soft-delete-aware');
    check(table_has_soft_delete('customers') === true, 'customers should be soft-delete-aware');
    check(table_has_soft_delete('sales') === true, 'sales should be soft-delete-aware');
    check(table_has_soft_delete('orders') === true, 'orders should be soft-delete-aware');
    check(table_has_soft_delete('stock') === true, 'stock should be soft-delete-aware');
});

test('table_has_soft_delete returns false for out-of-scope tables', function () {
    check(table_has_soft_delete('products') === false, 'products is out of scope');
    check(table_has_soft_delete('categories') === false, 'categories is out of scope');
    check(table_has_soft_delete('log') === false, 'log is out of scope');
    check(table_has_soft_delete('media') === false, 'media is out of scope');
    check(table_has_soft_delete('user_groups') === false, 'user_groups is out of scope');
});

test('table_has_soft_delete returns false for unknown table', function () {
    check(table_has_soft_delete('definitely_not_a_table') === false, 'unknown table should return false');
});
```

- [ ] **Step 2: Run the test and confirm it fails**

```bash
php tests/SoftDeleteTest.php
```

Expected: FAIL with "Call to undefined function table_has_soft_delete()" (or similar). Exit code 255.

- [ ] **Step 3: Add the helper to `includes/sql.php`**

Open `includes/sql.php`. After the closing brace of `delete_by_id` at line 117, insert this block:

```php

/*--------------------------------------------------------------*/
/* Soft-delete helpers (PR: soft-delete pattern, 2026-05-16).
/* In-scope tables: users, customers, sales, orders, stock.
/*--------------------------------------------------------------*/

/**
 * In-scope tables for the soft-delete pattern. Adding a table here is
 * NOT enough to enable soft-delete — the table also needs the
 * `deleted_at` column from the matching 005-009 migration.
 */
const SOFT_DELETE_TABLES = ['users', 'customers', 'sales', 'orders', 'stock'];

/**
 * Returns true when $table is in the in-scope allowlist AND its
 * `deleted_at` column exists. Cached per request. Never throws —
 * a probe failure returns false so the deploy-window fallback works.
 *
 * @param string $table
 * @return bool
 */
function table_has_soft_delete(string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    if (!in_array($table, SOFT_DELETE_TABLES, true)) {
        return $cache[$table] = false;
    }
    global $db;
    try {
        $r = $db->connection()->query(
            "SHOW COLUMNS FROM `" . $db->escape($table) . "` LIKE 'deleted_at'"
        );
        $has = ($r !== false && $r->num_rows > 0);
        if ($r) {
            $r->free();
        }
        return $cache[$table] = $has;
    } catch (\Throwable $e) {
        return $cache[$table] = false;
    }
}
```

- [ ] **Step 4: Run the test and confirm it passes**

```bash
php tests/SoftDeleteTest.php
```

Expected: 3 PASS lines (one per new test). Exit code 0.

- [ ] **Step 5: Run the full suite**

```bash
bash tests/run.sh
```

Expected: all 6 suites green.

- [ ] **Step 6: Commit**

```bash
git add includes/sql.php tests/SoftDeleteTest.php
git commit -m "feat(sql): table_has_soft_delete introspection helper"
```

---

## Task 8: Add `soft_delete_by_id`, `restore_by_id`, `purge_by_id`

Three sibling functions. `soft_delete_by_id` stamps the two new columns; `restore_by_id` clears them; `purge_by_id` runs the real DELETE — but only if the row is already soft-deleted.

**Files:**
- Modify: `includes/sql.php` (append below `table_has_soft_delete` from Task 7)
- Modify: `tests/SoftDeleteTest.php` (more cases)

- [ ] **Step 1: Write failing tests for the three new functions**

Append to `tests/SoftDeleteTest.php` above the closing `echo`:

```php
// Task 8 — soft-delete / restore / purge round-trip on a HARNESS_ user.
test('soft_delete_by_id stamps deleted_at and deleted_by', function () {
    global $db;
    // Insert a HARNESS_ user. Use mysqli directly to skip the registration UI.
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", 'HARNESS_softdel', 'HARNESS_softdel', 'x', 3, 1
    );
    $id = $db->connection()->insert_id;
    $stmt->close();
    check($id > 0, 'failed to insert HARNESS_softdel');

    $ok = soft_delete_by_id('users', $id, 1);
    check($ok === true, 'soft_delete_by_id returned false');

    $row = find_by_id_with_deleted('users', $id);
    check($row !== null, 'row not found after soft-delete');
    check($row['deleted_at'] !== null, 'deleted_at not set');
    check((int)$row['deleted_by'] === 1, 'deleted_by not set to actor id');
});

test('restore_by_id clears both timestamp columns', function () {
    global $db;
    $row = $db->prepare_select_one(
        "SELECT id FROM users WHERE username = ? LIMIT 1", "s", 'HARNESS_softdel'
    );
    $id = (int)$row['id'];

    $ok = restore_by_id('users', $id);
    check($ok === true, 'restore_by_id returned false');

    $row = find_by_id_with_deleted('users', $id);
    check($row !== null, 'row missing after restore');
    check($row['deleted_at'] === null, 'deleted_at not cleared');
    check($row['deleted_by'] === null, 'deleted_by not cleared');
});

test('purge_by_id refuses to remove an active row', function () {
    global $db;
    $row = $db->prepare_select_one(
        "SELECT id FROM users WHERE username = ? LIMIT 1", "s", 'HARNESS_softdel'
    );
    $id = (int)$row['id'];

    $ok = purge_by_id('users', $id);
    check($ok === false, 'purge should refuse active row');

    $row = find_by_id_with_deleted('users', $id);
    check($row !== null, 'row was purged despite refusal');
});

test('purge_by_id removes a soft-deleted row permanently', function () {
    global $db;
    $row = $db->prepare_select_one(
        "SELECT id FROM users WHERE username = ? LIMIT 1", "s", 'HARNESS_softdel'
    );
    $id = (int)$row['id'];

    check(soft_delete_by_id('users', $id, 1) === true, 'pre-purge soft-delete failed');
    check(purge_by_id('users', $id) === true, 'purge_by_id returned false');

    $row = find_by_id_with_deleted('users', $id);
    check($row === null, 'row still present after purge');
});
```

- [ ] **Step 2: Run tests; expect failure with "undefined function find_by_id_with_deleted"**

```bash
php tests/SoftDeleteTest.php
```

Expected: failures referencing undefined `soft_delete_by_id` / `find_by_id_with_deleted`.

- [ ] **Step 3: Add the three functions + `find_by_id_with_deleted` to `includes/sql.php`**

Append below `table_has_soft_delete` (the function from Task 7):

```php

/**
 * Soft-delete a row. Stamps deleted_at = NOW() and deleted_by = actor.
 * No-op when the table is not in scope.
 *
 * @param string $table
 * @param int $id
 * @param int|null $actor_user_id  Defaults to $_SESSION['user_id'].
 * @return bool  True when exactly one row was updated.
 */
function soft_delete_by_id(string $table, int $id, ?int $actor_user_id = null): bool {
    if (!table_has_soft_delete($table)) {
        return false;
    }
    if ($actor_user_id === null) {
        $actor_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
    global $db;
    $stmt = $db->prepare_query(
        "UPDATE `" . $db->escape($table) . "`
            SET deleted_at = NOW(), deleted_by = ?
          WHERE id = ? AND deleted_at IS NULL LIMIT 1",
        "ii", $actor_user_id, $id
    );
    $affected = $stmt->affected_rows;
    $stmt->close();
    return ($affected === 1);
}

/**
 * Reverse a soft-delete. Sets both deleted_at and deleted_by to NULL.
 *
 * @param string $table
 * @param int $id
 * @return bool  True when exactly one row was updated.
 */
function restore_by_id(string $table, int $id): bool {
    if (!table_has_soft_delete($table)) {
        return false;
    }
    global $db;
    $stmt = $db->prepare_query(
        "UPDATE `" . $db->escape($table) . "`
            SET deleted_at = NULL, deleted_by = NULL
          WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1",
        "i", $id
    );
    $affected = $stmt->affected_rows;
    $stmt->close();
    return ($affected === 1);
}

/**
 * Permanently delete a soft-deleted row. Refuses when the row is still
 * active (deleted_at IS NULL) — must be soft-deleted first.
 *
 * @param string $table
 * @param int $id
 * @return bool  True when one row was removed.
 */
function purge_by_id(string $table, int $id): bool {
    if (!table_has_soft_delete($table)) {
        return false;
    }
    global $db;
    $stmt = $db->prepare_query(
        "DELETE FROM `" . $db->escape($table) . "`
          WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1",
        "i", $id
    );
    $affected = $stmt->affected_rows;
    $stmt->close();
    return ($affected === 1);
}

/**
 * Same as find_by_id but does NOT filter out soft-deleted rows.
 * For the trash UI and audit lookups.
 *
 * @param string $table
 * @param int $id
 * @return array|null
 */
function find_by_id_with_deleted(string $table, int $id): ?array {
    global $db;
    if (!tableExists($table)) {
        return null;
    }
    return $db->prepare_select_one(
        "SELECT * FROM `" . $db->escape($table) . "` WHERE id = ? LIMIT 1",
        "i", (int)$id
    );
}
```

- [ ] **Step 4: Run tests and verify all 4 new cases pass**

```bash
php tests/SoftDeleteTest.php
```

Expected: 7 total PASS lines (3 from Task 7 + 4 from Task 8). Exit 0.

- [ ] **Step 5: Run full suite**

```bash
bash tests/run.sh
```

Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add includes/sql.php tests/SoftDeleteTest.php
git commit -m "feat(sql): soft_delete_by_id / restore_by_id / purge_by_id + find_by_id_with_deleted"
```

---

## Task 9: Auto-filter `find_all` and `find_by_id`

Modify the two generic helpers to append `WHERE deleted_at IS NULL` when `table_has_soft_delete($table)` is true. Add the matching `find_with_deleted($table)` and confirm the auto-filter does not break CRUDTest.

**Files:**
- Modify: `includes/sql.php` lines 21-26 (`find_all`) and 59-68 (`find_by_id`); append `find_with_deleted`.
- Modify: `tests/SoftDeleteTest.php` — new cases.

- [ ] **Step 1: Add failing tests for the auto-filter**

Append to `tests/SoftDeleteTest.php`:

```php
// Task 9 — generic helpers auto-filter soft-deleted rows.
test('find_all excludes soft-deleted rows', function () {
    global $db;
    // Create + soft-delete a HARNESS_ user.
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", 'HARNESS_findfilter', 'HARNESS_findfilter', 'x', 3, 1
    );
    $id = $db->connection()->insert_id;
    $stmt->close();
    soft_delete_by_id('users', $id, 1);

    $all = find_all('users');
    $ids = array_column($all, 'id');
    check(!in_array($id, array_map('intval', $ids), true),
        "find_all('users') leaked soft-deleted id=$id");

    // Cleanup
    purge_by_id('users', $id);
});

test('find_by_id returns null for a soft-deleted row', function () {
    global $db;
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", 'HARNESS_findbyid', 'HARNESS_findbyid', 'x', 3, 1
    );
    $id = $db->connection()->insert_id;
    $stmt->close();
    soft_delete_by_id('users', $id, 1);

    $row = find_by_id('users', $id);
    check($row === null, 'find_by_id returned a soft-deleted row');

    $row2 = find_by_id_with_deleted('users', $id);
    check($row2 !== null && (int)$row2['id'] === $id,
        'find_by_id_with_deleted failed to return the soft-deleted row');

    purge_by_id('users', $id);
});

test('find_with_deleted returns ALL rows including soft-deleted', function () {
    global $db;
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", 'HARNESS_withdel', 'HARNESS_withdel', 'x', 3, 1
    );
    $id = $db->connection()->insert_id;
    $stmt->close();
    soft_delete_by_id('users', $id, 1);

    $all = find_with_deleted('users');
    $ids = array_map('intval', array_column($all, 'id'));
    check(in_array($id, $ids, true), 'find_with_deleted did not include soft-deleted id');

    purge_by_id('users', $id);
});
```

- [ ] **Step 2: Run; expect failures**

```bash
php tests/SoftDeleteTest.php
```

Expected: FAIL on the auto-filter cases ("leaked soft-deleted id") and undefined `find_with_deleted`.

- [ ] **Step 3: Modify `find_all` (lines 21-26 of `includes/sql.php`)**

Replace the existing body:

```php
function find_all($table) {
    global $db;
    if (tableExists($table)) {
        return find_by_sql("SELECT * FROM ".$db->escape($table));
    }
}
```

with:

```php
function find_all($table) {
    global $db;
    if (!tableExists($table)) {
        return null;
    }
    $sql = "SELECT * FROM " . $db->escape($table);
    if (table_has_soft_delete($table)) {
        $sql .= " WHERE deleted_at IS NULL";
    }
    return find_by_sql($sql);
}
```

- [ ] **Step 4: Modify `find_by_id` (lines 59-68)**

Replace:

```php
function find_by_id($table, $id) {
    global $db;
    if (tableExists($table)) {
        return $db->prepare_select_one(
            "SELECT * FROM {$db->escape($table)} WHERE id = ? LIMIT 1",
            "i", (int)$id
        );
    }
    return null;
}
```

with:

```php
function find_by_id($table, $id) {
    global $db;
    if (!tableExists($table)) {
        return null;
    }
    $where = "WHERE id = ?";
    if (table_has_soft_delete($table)) {
        $where .= " AND deleted_at IS NULL";
    }
    return $db->prepare_select_one(
        "SELECT * FROM {$db->escape($table)} {$where} LIMIT 1",
        "i", (int)$id
    );
}
```

- [ ] **Step 5: Append `find_with_deleted` below the helpers added in Task 8**

```php
/**
 * Same as find_all but does NOT filter out soft-deleted rows.
 * For the trash UI and audit/export lookups.
 *
 * @param string $table
 * @return array|null
 */
function find_with_deleted(string $table): ?array {
    global $db;
    if (!tableExists($table)) {
        return null;
    }
    return find_by_sql("SELECT * FROM " . $db->escape($table));
}
```

- [ ] **Step 6: Run SoftDeleteTest — all 10 cases should pass**

```bash
php tests/SoftDeleteTest.php
```

Expected: 10 PASS lines, 0 FAIL.

- [ ] **Step 7: Run CRUDTest specifically to confirm the auto-filter did not break existing happy paths**

```bash
php tests/CRUDTest.php
```

Expected: PASS. If CRUDTest creates a row, soft-deletes it (it doesn't — it hard-deletes), and reads it back, the soft-delete dimension never enters. The auto-filter is invisible to existing tests.

- [ ] **Step 8: Run full suite**

```bash
bash tests/run.sh
```

Expected: all green.

- [ ] **Step 9: Commit**

```bash
git add includes/sql.php tests/SoftDeleteTest.php
git commit -m "feat(sql): auto-filter find_all + find_by_id; add find_with_deleted"
```

---

## Task 10: Grep audit + hand-edit raw-SQL helpers

The generic auto-filter does not reach functions that call `find_by_sql` or `$db->prepare_select` directly. Each must be edited.

**Files:**
- Modify: `includes/sql.php` — specific functions enumerated by the grep audit.
- Modify: `tests/SoftDeleteTest.php` — new cases that exercise the raw-SQL helpers.

- [ ] **Step 1: Run the grep audit to enumerate all raw-SQL helpers touching in-scope tables**

```bash
grep -nE "FROM (users|customers|sales|orders|stock)( |$| u| c| s| o)" includes/sql.php
```

Known required edits (verify with the grep):

| Line | Function | Edit |
|---|---|---|
| 295 | `find_all_user()` | Add `WHERE u.deleted_at IS NULL` to the SELECT |
| 554 | `find_customer_by_name()` | Add `AND deleted_at IS NULL` |
| 576 | `find_all_customer_info_by_name()` | Add `AND deleted_at IS NULL` |
| 759 | `find_all_sales()` | Add `WHERE s.deleted_at IS NULL` |
| 779 | `find_all_orders()` | Add `WHERE o.deleted_at IS NULL` |
| 799 | `find_sales_by_order_id()` | Add `AND s.deleted_at IS NULL` |
| 823 | `find_recent_sale_added()` | Add `AND s.deleted_at IS NULL` if SELECT FROM sales |
| 844 | `find_sale_by_dates()` | Same |

Read each function's existing body before editing; the grep output is the canonical list — extend the table above if the grep finds more.

- [ ] **Step 2: Add failing tests for two representative raw-SQL helpers**

Append to `tests/SoftDeleteTest.php`:

```php
// Task 10 — raw-SQL helpers also filter soft-deleted rows.
test('find_all_user excludes soft-deleted users', function () {
    global $db;
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", 'HARNESS_rawsql', 'HARNESS_rawsql', 'x', 3, 1
    );
    $id = $db->connection()->insert_id;
    $stmt->close();
    soft_delete_by_id('users', $id, 1);

    $rows = find_all_user();
    $ids = array_map('intval', array_column($rows, 'id'));
    check(!in_array($id, $ids, true), 'find_all_user leaked soft-deleted id');

    purge_by_id('users', $id);
});

test('find_all_sales excludes soft-deleted sales (row-local cascade test)', function () {
    global $db;
    // Insert a HARNESS_ sale row directly.
    $stmt = $db->prepare_query(
        "INSERT INTO sales (order_id, product_id, qty, price, date) VALUES (?, ?, ?, ?, ?)",
        "iiids", 1, 1, 1, 1.00, date('Y-m-d')
    );
    $id = $db->connection()->insert_id;
    $stmt->close();

    $rows = find_all_sales();
    $ids = array_map('intval', array_column($rows, 'id'));
    check(in_array($id, $ids, true), 'find_all_sales did not include the new sale');

    soft_delete_by_id('sales', $id, 1);

    $rows = find_all_sales();
    $ids = array_map('intval', array_column($rows, 'id'));
    check(!in_array($id, $ids, true), 'find_all_sales leaked soft-deleted sale');

    purge_by_id('sales', $id);
});
```

- [ ] **Step 3: Confirm tests fail**

```bash
php tests/SoftDeleteTest.php
```

Expected: FAIL on the two new cases — the raw-SQL functions don't filter yet.

- [ ] **Step 4: Edit `find_all_user()` at line 295**

Replace:
```php
$sql = "SELECT u.id,u.name,u.username,u.user_level,u.status,u.last_login,";
$sql .="g.group_name ";
$sql .="FROM users u ";
$sql .="LEFT JOIN user_groups g ";
$sql .="ON g.group_level=u.user_level ORDER BY u.name ASC";
```

with:
```php
$sql = "SELECT u.id,u.name,u.username,u.user_level,u.status,u.last_login,";
$sql .="g.group_name ";
$sql .="FROM users u ";
$sql .="LEFT JOIN user_groups g ";
$sql .="ON g.group_level=u.user_level ";
$sql .="WHERE u.deleted_at IS NULL ";
$sql .="ORDER BY u.name ASC";
```

- [ ] **Step 5: Edit `find_all_sales()` at line 759**

Replace:
```php
$sql  = "SELECT s.id,s.order_id,s.qty,s.price,s.date,p.name";
$sql .= " FROM sales s";
$sql .= " LEFT JOIN orders o ON s.order_id = o.id";
$sql .= " LEFT JOIN products p ON s.product_id = p.id";
$sql .= " ORDER BY s.date DESC";
```

with:
```php
$sql  = "SELECT s.id,s.order_id,s.qty,s.price,s.date,p.name";
$sql .= " FROM sales s";
$sql .= " LEFT JOIN orders o ON s.order_id = o.id";
$sql .= " LEFT JOIN products p ON s.product_id = p.id";
$sql .= " WHERE s.deleted_at IS NULL";
$sql .= " ORDER BY s.date DESC";
```

- [ ] **Step 6: Edit `find_all_orders()` at line 779**

Replace:
```php
$sql  = "SELECT o.id,o.sales_id,o.date";
$sql .= " FROM orders o";
$sql .= " LEFT JOIN sales s ON s.id = o.sales_id";
$sql .= " ORDER BY o.date DESC";
```

with:
```php
$sql  = "SELECT o.id,o.sales_id,o.date";
$sql .= " FROM orders o";
$sql .= " LEFT JOIN sales s ON s.id = o.sales_id";
$sql .= " WHERE o.deleted_at IS NULL";
$sql .= " ORDER BY o.date DESC";
```

- [ ] **Step 7: Edit the remaining helpers from the grep table**

For each remaining entry (`find_customer_by_name`, `find_all_customer_info_by_name`, `find_sales_by_order_id`, `find_recent_sale_added`, `find_sale_by_dates`, plus anything the grep audit surfaced): read the existing body, identify whether the in-scope table is the FROM clause primary or only a join, and append `AND <alias>.deleted_at IS NULL` to the existing WHERE (or add a new WHERE clause when none exists). Use the patterns from Steps 4-6 as the template.

For `find_sales_by_order_id` at line 799, the existing WHERE is `WHERE s.order_id = ?` — extend to `WHERE s.order_id = ? AND s.deleted_at IS NULL`.

For `find_customer_by_name` at line 554 and `find_all_customer_info_by_name` at line 576, the existing WHEREs use `customers WHERE name = ?` (no alias) — extend with `AND deleted_at IS NULL`.

- [ ] **Step 8: Run all tests**

```bash
bash tests/run.sh
```

Expected: SoftDeleteTest's two new cases pass; full suite green. If CRUDTest fails on a sales/orders/customers/users query, inspect — the auto-filter or the raw-SQL edit may be over-aggressive (e.g., filtering a row CRUDTest just inserted because the test framework set `deleted_at` somehow). Verify by running each suite individually.

- [ ] **Step 9: Commit**

```bash
git add includes/sql.php tests/SoftDeleteTest.php
git commit -m "feat(sql): hand-edit raw-SQL helpers for in-scope tables"
```

---

## Task 11: Route the 5 delete pages through `soft_delete_by_id`

The existing delete pages each call `delete_by_id($table, $id)` and treat the boolean return as "deletion succeeded". They get one-line edits.

**Files:**
- Modify: `users/delete_user.php:15`
- Modify: `customers/delete_customer.php:23`
- Modify: `sales/delete_sale.php:32`
- Modify: `sales/delete_order.php:26` AND `:31`
- Modify: `products/delete_stock.php:26`

- [ ] **Step 1: Edit `users/delete_user.php:15`**

Replace:
```php
$delete_id = delete_by_id('users', (int)$_GET['id']);
```

with:
```php
$delete_id = soft_delete_by_id('users', (int)$_GET['id']);
```

The success message stays "User deleted." — to the admin, soft-delete is "delete". Restore happens from the trash page.

- [ ] **Step 2: Edit `customers/delete_customer.php`**

Find the `delete_by_id('customers', ...)` line. Replace with `soft_delete_by_id('customers', ...)`. Keep the surrounding code intact.

- [ ] **Step 3: Edit `sales/delete_sale.php`**

Find the `delete_by_id('sales', ...)` line. Replace with `soft_delete_by_id('sales', ...)`.

- [ ] **Step 4: Edit both calls in `sales/delete_order.php`**

The file has two calls — a `delete_by_id('sales', ...)` inside a loop that nukes the order's child sales, then a `delete_by_id('orders', ...)` for the order row itself. Replace both with their `soft_delete_by_id(...)` equivalents.

This is the row-local cascade rule from the spec: explicit code soft-deleting both rows is fine; what we are NOT doing is auto-cascading at the DB layer.

- [ ] **Step 5: Edit `products/delete_stock.php`**

Find the `delete_by_id('stock', ...)` line. Replace with `soft_delete_by_id('stock', ...)`.

- [ ] **Step 6: Manual smoke test on dev**

Use the live app at `http://localhost:8080`. Log in as admin. For each of the 5 tables, navigate to the listing, click delete on a HARNESS_-prefixed row (create one first if absent), confirm the row disappears from the listing, then verify the row is still in the DB with `deleted_at IS NOT NULL`:

```bash
sudo mysql inventory -e "SELECT id, name, deleted_at, deleted_by FROM users WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 5;"
sudo mysql inventory -e "SELECT id, name, deleted_at, deleted_by FROM customers WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 5;"
sudo mysql inventory -e "SELECT id, deleted_at, deleted_by FROM sales WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 5;"
sudo mysql inventory -e "SELECT id, deleted_at, deleted_by FROM orders WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 5;"
sudo mysql inventory -e "SELECT id, deleted_at, deleted_by FROM stock WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 5;"
```

Each query should show the recently soft-deleted HARNESS_ row with `deleted_by` = your admin user id.

- [ ] **Step 7: Run full suite**

```bash
bash tests/run.sh
```

Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add users/delete_user.php customers/delete_customer.php sales/delete_sale.php sales/delete_order.php products/delete_stock.php
git commit -m "feat(delete-pages): route 5 audit-heavy tables through soft_delete_by_id"
```

---

## Task 12: Trash UI — `users/trash.php`

Admin-only listing of soft-deleted rows, one tab per in-scope table, with per-row Restore and Purge buttons.

**Files:**
- Create: `users/trash.php`

- [ ] **Step 1: Create the file**

```php
<?php
/**
 * users/trash.php
 *
 * Admin-only trash page. Lists soft-deleted rows per table with Restore
 * and Purge actions. Tabs across the top switch tables via ?table=...
 * 5 supported tables: users, customers, sales, orders, stock.
 */

require_once '../includes/load.php';
page_require_level(1);

// SOFT_DELETE_TABLES is defined in includes/sql.php (Task 7).
$table = isset($_GET['table']) ? (string)$_GET['table'] : 'users';
if (!in_array($table, SOFT_DELETE_TABLES, true)) {
    $session->msg('d', 'Invalid trash table.');
    redirect('trash.php?table=users', false);
}

$rows = find_with_deleted($table);
// Keep only soft-deleted rows.
$rows = array_values(array_filter($rows, function ($r) {
    return !empty($r['deleted_at']);
}));

// Per-table label-column projector. Falls back to id only.
function trash_label_columns(string $table, array $row): array {
    switch ($table) {
        case 'users':
            return ['username' => $row['username'] ?? '', 'name' => $row['name'] ?? ''];
        case 'customers':
            return ['name' => $row['name'] ?? ''];
        case 'sales':
            return ['date' => $row['date'] ?? '', 'qty' => $row['qty'] ?? '', 'price' => $row['price'] ?? ''];
        case 'orders':
            return ['customer' => $row['customer'] ?? '', 'date' => $row['date'] ?? ''];
        case 'stock':
            $product = !empty($row['product_id']) ? find_by_id('products', (int)$row['product_id']) : null;
            return [
                'product' => $product['name'] ?? ('product #' . ($row['product_id'] ?? '?')),
                'quantity' => $row['quantity'] ?? '',
                'date' => $row['date'] ?? '',
            ];
    }
    return [];
}

$page_title = 'Trash';
include_once('../layouts/header.php');
?>
<div class="row">
  <div class="col-md-12">
    <?php echo display_msg($msg); ?>
    <div class="panel panel-default">
      <div class="panel-heading clearfix">
        <strong><span class="glyphicon glyphicon-trash"></span> Trash</strong>
      </div>
      <ul class="nav nav-tabs">
        <?php foreach (SOFT_DELETE_TABLES as $t):
            $active = ($t === $table) ? ' class="active"' : '';
        ?>
          <li<?php echo $active; ?>>
            <a href="trash.php?table=<?php echo h($t); ?>"><?php echo h(ucfirst($t)); ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="panel-body">
        <?php if (empty($rows)): ?>
          <p>No soft-deleted <?php echo h($table); ?> rows.</p>
        <?php else: ?>
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>ID</th>
                <?php foreach (array_keys(trash_label_columns($table, $rows[0])) as $label_col): ?>
                  <th><?php echo h(ucfirst($label_col)); ?></th>
                <?php endforeach; ?>
                <th>Deleted at</th>
                <th>Deleted by</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row):
                $labels = trash_label_columns($table, $row);
                $deleter = !empty($row['deleted_by'])
                    ? find_by_id_with_deleted('users', (int)$row['deleted_by'])
                    : null;
                $deleter_name = $deleter ? ($deleter['username'] ?? ('user #' . $deleter['id'])) : '—';
              ?>
                <tr>
                  <td><?php echo (int)$row['id']; ?></td>
                  <?php foreach ($labels as $val): ?>
                    <td><?php echo h((string)$val); ?></td>
                  <?php endforeach; ?>
                  <td><?php echo h($row['deleted_at']); ?></td>
                  <td><?php echo h($deleter_name); ?></td>
                  <td>
                    <form method="post" action="restore.php" style="display:inline">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="table" value="<?php echo h($table); ?>">
                      <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                      <button type="submit" class="btn btn-success btn-xs">Restore</button>
                    </form>
                    <form method="post" action="purge.php" style="display:inline"
                          onsubmit="return confirm('Permanently delete this row? This cannot be undone.');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="table" value="<?php echo h($table); ?>">
                      <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                      <button type="submit" class="btn btn-danger btn-xs">Purge</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include_once('../layouts/footer.php'); ?>
```

- [ ] **Step 2: `php -l` syntax check**

```bash
php -l users/trash.php
```

Expected: `No syntax errors detected in users/trash.php`.

- [ ] **Step 3: Manual smoke**

Soft-delete a HARNESS_ user via the admin UI, then visit `http://localhost:8080/users/trash.php`. Expected:
- Page renders with the 5 tabs across the top, "Users" active by default.
- The HARNESS_ row appears with id, username, name, deleted_at, deleted_by columns.
- Restore and Purge buttons render. (They don't work yet — endpoints come in the next task.)
- Click each tab — pages render with "No soft-deleted &lt;table&gt; rows" until you also soft-delete something in that table.

- [ ] **Step 4: Commit**

```bash
git add users/trash.php
git commit -m "feat(trash): admin-only trash listing with per-table tabs"
```

---

## Task 13: Restore + Purge POST endpoints

Two small POST endpoints, each CSRF-protected, each validating the `table` against the same allowlist.

**Files:**
- Create: `users/restore.php`
- Create: `users/purge.php`

- [ ] **Step 1: Create `users/restore.php`**

```php
<?php
/**
 * users/restore.php
 *
 * POST endpoint to restore a soft-deleted row. CSRF + admin gated.
 */

require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('trash.php', false);
}
if (!verify_csrf()) {
    $session->msg('d', 'Invalid or missing security token.');
    redirect('trash.php', false);
}

// SOFT_DELETE_TABLES is defined in includes/sql.php (Task 7).
$table = isset($_POST['table']) ? (string)$_POST['table'] : '';
$id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!in_array($table, SOFT_DELETE_TABLES, true) || $id <= 0) {
    $session->msg('d', 'Invalid restore request.');
    redirect('trash.php', false);
}

if (restore_by_id($table, $id)) {
    $session->msg('s', ucfirst($table) . " row #{$id} restored.");
} else {
    $session->msg('d', "Restore failed (row #{$id} not soft-deleted, or table not in scope).");
}
redirect('trash.php?table=' . urlencode($table), false);
```

- [ ] **Step 2: Create `users/purge.php`**

```php
<?php
/**
 * users/purge.php
 *
 * POST endpoint to permanently delete a soft-deleted row. CSRF + admin
 * gated. Refuses to purge an active row — must be soft-deleted first.
 */

require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('trash.php', false);
}
if (!verify_csrf()) {
    $session->msg('d', 'Invalid or missing security token.');
    redirect('trash.php', false);
}

// SOFT_DELETE_TABLES is defined in includes/sql.php (Task 7).
$table = isset($_POST['table']) ? (string)$_POST['table'] : '';
$id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!in_array($table, SOFT_DELETE_TABLES, true) || $id <= 0) {
    $session->msg('d', 'Invalid purge request.');
    redirect('trash.php', false);
}

if (purge_by_id($table, $id)) {
    $session->msg('s', ucfirst($table) . " row #{$id} permanently deleted.");
} else {
    $session->msg('d', "Purge refused (row #{$id} is not soft-deleted, or table not in scope).");
}
redirect('trash.php?table=' . urlencode($table), false);
```

- [ ] **Step 3: `php -l` both files**

```bash
php -l users/restore.php
php -l users/purge.php
```

Expected: both report "No syntax errors detected".

- [ ] **Step 4: Manual smoke — full round-trip**

In a browser logged in as admin:
1. Create a HARNESS_ user.
2. Click delete (calls `soft_delete_by_id`).
3. Open `users/trash.php?table=users` — confirm the row is listed.
4. Click Restore — confirm a green flash message + the row disappears from the trash listing.
5. Verify the row reappears in `users/users.php`.
6. Click delete again, return to trash.
7. Click Purge, confirm the warning, accept.
8. Confirm a green "permanently deleted" flash.
9. Verify `SELECT id FROM users WHERE username='HARNESS_xxx';` returns 0 rows.

- [ ] **Step 5: Commit**

```bash
git add users/restore.php users/purge.php
git commit -m "feat(trash): restore + purge POST endpoints"
```

---

## Task 14: Add the "Trash" link to the admin menu

**Files:**
- Modify: `layouts/admin_menu.php` (one new `<li>` near the Settings link at line 71)

- [ ] **Step 1: Add the menu link**

In `layouts/admin_menu.php`, find the existing Settings line:

```html
<li><a href="../users/settings.php">Settings</a> </li>
```

Add directly below it:

```html
<li><a href="../users/trash.php">Trash</a> </li>
```

The link inherits the surrounding admin-only block — it does not need its own gate.

- [ ] **Step 2: Manual check**

Reload any admin page. The "User Management" sidebar group should now show: ... Settings, Trash.

- [ ] **Step 3: Commit**

```bash
git add layouts/admin_menu.php
git commit -m "feat(menu): Trash link under User Management"
```

---

## Task 15: Final regression run, push, open PR

**Files:**
- None — verification and git only.

- [ ] **Step 1: Run the full test suite one more time**

```bash
bash tests/run.sh
```

Expected: all 6 suites green:
```
Results: 6/6 suites passed
```

- [ ] **Step 2: Confirm migrations index in `migrations/README.md` matches the live state**

```bash
grep -E "^\| 00[5-9]" migrations/README.md
```

Expected: 5 rows for 005-009.

- [ ] **Step 3: Confirm pre-commit hook is green on the branch**

```bash
git log --oneline feature/soft-delete-pattern ^main | head
```

Pre-commit hooks ran on each commit; if any had been red, the commit would not exist.

- [ ] **Step 4: Push**

```bash
git push -u origin feature/soft-delete-pattern
```

- [ ] **Step 5: Open the PR**

```bash
gh pr create --title "feat: soft-delete pattern on 5 audit-heavy tables" --body "$(cat <<'EOF'
## Summary

Implements the soft-delete sub-project described in `docs/superpowers/specs/2026-05-16-soft-delete-design.md` (first of three deferred items from `next_steps_inventory.md`).

- 5 in-scope tables: users, customers, sales, orders, stock.
- 5 paired migrations (005-009) add `deleted_at` + `deleted_by` columns, FK to `users(id) ON DELETE SET NULL` (mirrors `fk_log_user` from PR #27).
- `schema.sql` mirrored so fresh installs / CI start in the new shape.
- New helpers in `includes/sql.php`: `soft_delete_by_id`, `restore_by_id`, `purge_by_id`, `find_with_deleted`, `find_by_id_with_deleted`, `table_has_soft_delete`.
- `find_all` and `find_by_id` auto-filter `WHERE deleted_at IS NULL` via cached schema introspection.
- Raw-SQL helpers (`find_all_user`, `find_all_sales`, `find_all_orders`, etc.) hand-edited per the in-scope table list.
- 5 delete pages route through `soft_delete_by_id`.
- New admin-only trash UI: `users/trash.php` + `users/restore.php` + `users/purge.php`. Linked from User Management sidebar.
- New `tests/SoftDeleteTest.php` (10+ cases) wired into `tests/run.sh`.

## Deploy

```bash
sudo mysqldump --single-transaction inventory > inventory-pre-005-009.sql
sudo mysql inventory < migrations/005_users_soft_delete.up.sql
sudo mysql inventory < migrations/006_customers_soft_delete.up.sql
sudo mysql inventory < migrations/007_sales_soft_delete.up.sql
sudo mysql inventory < migrations/008_orders_soft_delete.up.sql
sudo mysql inventory < migrations/009_stock_soft_delete.up.sql
```

`table_has_soft_delete()` falls back to `false` when columns are absent — the deploy → migrate window serves correct (unfiltered) reads.

## Test plan

- [ ] Full local suite green (6/6 suites)
- [ ] Soft-delete + restore + purge round-trip in browser for each of 5 tables
- [ ] Verify row-local cascade: soft-deleting a customer leaves their sales visible in the sales list
- [ ] Pre-commit hooks green on every commit
EOF
)"
```

---

## Open follow-ups (out of scope; record in `next_steps_inventory.md` after merge)

- Bulk-restore / bulk-purge in the trash UI.
- Auto-purge retention policy (e.g., purge anything with `deleted_at < NOW() - INTERVAL 90 DAY`).
- Apply pattern to products + categories if future use cases demand it.
- Hook into a hypothetical `log_admin_action()` from restore/purge endpoints.

---

## Sub-project 2 and 3 (NOT in this plan)

- **Tenancy + per-org currency** — separate brainstorm + spec + plan.
- **Playwright UI tests** — separate brainstorm + spec + plan.

Both depend on this PR being merged so the soft-delete schema is settled before tenancy migrations land or Playwright records baselines.
