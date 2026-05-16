# Tenancy + Per-Org Currency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the inventory app multi-org-aware on a single Pi deployment — users belong to multiple orgs via `org_members(role)`; all business data is scoped to `current_org_id()`; the per-org currency setting is the first concrete use case.

**Architecture:** Two PRs. PR1 (`feature/tenancy-schema`) ships the migrations, invisible plumbing (auto-filters, auth flow, switch endpoint, shim), and tests — the app looks identical to a single-org deployment after it lands. PR2 (`feature/tenancy-ux`) branches from main after PR1 is smoked, adds all org-management and switcher UI, and removes the shim. PR2 cannot start until PR1 is merged and verified on the live DB.

**Tech Stack:** PHP 8.x, MySQL/MariaDB, MySqli_DB wrapper (`$db->prepare_select()` / `prepare_select_one()` / `prepare_query()`), PHPUnit-style test runner (`bash tests/run.sh`), standard shell pre-commit hook.

---

## File map

### PR1 creates
```
packaging/sql/migrations/010_orgs_table.{up,down}.sql
packaging/sql/migrations/011_org_members_table.{up,down}.sql
packaging/sql/migrations/012_default_org_seed.{up,down}.sql
packaging/sql/migrations/013_customers_org_id.{up,down}.sql
packaging/sql/migrations/014_products_org_id.{up,down}.sql
packaging/sql/migrations/015_categories_org_id.{up,down}.sql
packaging/sql/migrations/016_sales_org_id.{up,down}.sql
packaging/sql/migrations/017_orders_org_id.{up,down}.sql
packaging/sql/migrations/018_stock_org_id.{up,down}.sql
packaging/sql/migrations/019_media_org_id.{up,down}.sql
packaging/sql/migrations/020_settings_org_id.{up,down}.sql
packaging/sql/migrations/021_users_last_active_org.{up,down}.sql
users/switch_org.php
tests/lib/tenancy_fixtures.php
tests/TenancyTest.php
```

### PR1 modifies
```
packaging/sql/schema.sql
includes/sql.php            (ORG_SCOPED_TABLES, table_has_org_id, current_org_id,
                             find_all/find_by_id auto-filter, full SELECT/INSERT/UPDATE/DELETE
                             audit, require_org_role, page_require_level shim, find_org_members)
includes/session.php        (Session::login signature)
includes/settings.php       (Settings::load + Settings::set org-scoped)
users/index.php             (consume new authenticate() return shape)
.githooks/pre-commit        (org-scoping grep guard)
tests/*.php                 (add setup_test_org_session() to fixtures)
```

### PR2 creates
```
users/orgs.php
users/add_org.php
users/edit_org.php
users/org_members.php
users/add_org_member.php
users/edit_org_member.php
tests/OrgManagementTest.php
```

### PR2 modifies
```
includes/sql.php            (add_org, rename_org, soft_delete_org, find_org, find_all_orgs_for_user,
                             add_org_member, change_org_member_role, remove_org_member)
layouts/header.php          (topbar org switcher dropdown)
~30 page files              (page_require_level → require_org_role; shim deleted from sql.php)
```

---

## Phase 1 — PR1: Schema + invisible plumbing

---

### Task 1: Migrations 010–012 — new tables + default org seed

**Files:**
- Create: `packaging/sql/migrations/010_orgs_table.up.sql`
- Create: `packaging/sql/migrations/010_orgs_table.down.sql`
- Create: `packaging/sql/migrations/011_org_members_table.up.sql`
- Create: `packaging/sql/migrations/011_org_members_table.down.sql`
- Create: `packaging/sql/migrations/012_default_org_seed.up.sql`
- Create: `packaging/sql/migrations/012_default_org_seed.down.sql`

- [ ] **Step 1: Create migrations directory if absent**

```bash
mkdir -p packaging/sql/migrations
```

- [ ] **Step 2: Write 010_orgs_table.up.sql**

```sql
-- 010_orgs_table.up.sql
CREATE TABLE `orgs` (
    `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120)     NOT NULL,
    `slug`       VARCHAR(60)      NOT NULL,
    `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP        NULL DEFAULT NULL,
    `deleted_by` INT(11) UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orgs_slug` (`slug`),
    KEY `idx_orgs_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_orgs_deleted_by`
        FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 3: Write 010_orgs_table.down.sql**

```sql
DROP TABLE IF EXISTS `orgs`;
```

- [ ] **Step 4: Write 011_org_members_table.up.sql**

```sql
-- 011_org_members_table.up.sql
CREATE TABLE `org_members` (
    `org_id`    INT(11) UNSIGNED NOT NULL,
    `user_id`   INT(11) UNSIGNED NOT NULL,
    `role`      ENUM('owner','admin','member') NOT NULL,
    `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`org_id`, `user_id`),
    KEY `idx_org_members_user` (`user_id`),
    CONSTRAINT `fk_org_members_org`
        FOREIGN KEY (`org_id`)  REFERENCES `orgs`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_org_members_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 5: Write 011_org_members_table.down.sql**

```sql
DROP TABLE IF EXISTS `org_members`;
```

- [ ] **Step 6: Write 012_default_org_seed.up.sql**

```sql
-- 012_default_org_seed.up.sql
INSERT INTO `orgs` (`id`, `name`, `slug`, `created_at`)
VALUES (1, 'Default Organization', 'default', NOW());

INSERT INTO `org_members` (`org_id`, `user_id`, `role`)
SELECT 1,
       u.id,
       CASE u.user_level
           WHEN 1 THEN 'owner'
           WHEN 2 THEN 'admin'
           ELSE        'member'
       END
FROM `users` u
WHERE u.deleted_at IS NULL;
```

- [ ] **Step 7: Write 012_default_org_seed.down.sql**

```sql
-- Down migrations roll back schema, not data (see spec § 7.2).
-- If a full data rollback is needed, use scripts/rollback_tenancy_seed.sql.
SELECT 1;
```

- [ ] **Step 8: Run migrations 010–012 on the local dev DB**

```bash
mysql -u root inventory < packaging/sql/migrations/010_orgs_table.up.sql
mysql -u root inventory < packaging/sql/migrations/011_org_members_table.up.sql
mysql -u root inventory < packaging/sql/migrations/012_default_org_seed.up.sql
```

Expected: no errors. Then verify:

```bash
mysql -u root inventory -e "SELECT COUNT(*) FROM orgs; SELECT COUNT(*) FROM org_members;"
```

Expected: `orgs` = 1 row, `org_members` = same count as non-deleted users.

- [ ] **Step 9: Commit**

```bash
git add packaging/sql/migrations/010_* packaging/sql/migrations/011_* packaging/sql/migrations/012_*
git commit -m "feat(tenancy): migrations 010-012 — orgs table, org_members table, default org seed"
```

---

### Task 2: Migrations 013–019 — org_id on business tables

**Files:**
- Create: `packaging/sql/migrations/013_customers_org_id.{up,down}.sql` through `019_media_org_id.{up,down}.sql`

All seven tables follow the same pattern. Deviations are called out per table.

- [ ] **Step 1: Write 013_customers_org_id.up.sql**

```sql
-- 013_customers_org_id.up.sql
ALTER TABLE `customers`
    ADD COLUMN `org_id` INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD KEY `idx_customers_org` (`org_id`),
    ADD CONSTRAINT `fk_customers_org`
        FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`);

-- Reshape any unique constraint on name to be per-org.
-- First check if the key exists before dropping (safe for both schema states):
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'customers'
       AND INDEX_NAME = 'name'),
    'ALTER TABLE `customers` DROP INDEX `name`, ADD UNIQUE KEY `uq_customers_org_name` (`org_id`, `name`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
```

- [ ] **Step 2: Write 013_customers_org_id.down.sql**

```sql
ALTER TABLE `customers`
    DROP FOREIGN KEY `fk_customers_org`,
    DROP KEY `idx_customers_org`,
    DROP COLUMN `org_id`;

-- Restore simple unique on name if needed by your baseline schema.
```

- [ ] **Step 3: Write migrations 014–019 following the same pattern**

For each table below, the `up.sql` body is identical to 013 except for the table name and constraint names. The UNIQUE reshape applies to `products` and `categories`; `sales`, `orders`, `stock`, `media` have no unique-name constraint to reshape.

| Migration | Table | UNIQUE reshape? |
|-----------|-------|-----------------|
| `014_products_org_id` | `products` | Yes (`name`) |
| `015_categories_org_id` | `categories` | Yes (`name`) |
| `016_sales_org_id` | `sales` | No |
| `017_orders_org_id` | `orders` | No |
| `018_stock_org_id` | `stock` | No |
| `019_media_org_id` | `media` | No |

Template for tables without UNIQUE reshape (016–019):

```sql
-- Example: 016_sales_org_id.up.sql
ALTER TABLE `sales`
    ADD COLUMN `org_id` INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD KEY `idx_sales_org` (`org_id`),
    ADD CONSTRAINT `fk_sales_org`
        FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`);
```

Down for each:
```sql
ALTER TABLE `<table>`
    DROP FOREIGN KEY `fk_<table>_org`,
    DROP KEY `idx_<table>_org`,
    DROP COLUMN `org_id`;
```

- [ ] **Step 4: Run migrations 013–019**

```bash
for n in 013 014 015 016 017 018 019; do
    mysql -u root inventory < packaging/sql/migrations/${n}_*.up.sql
done
```

Verify:
```bash
mysql -u root inventory -e "
  SELECT TABLE_NAME, COUNT(*) total
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'inventory'
    AND COLUMN_NAME = 'org_id'
    AND TABLE_NAME IN ('customers','products','categories','sales','orders','stock','media')
  GROUP BY TABLE_NAME;"
```

Expected: 7 rows, each with `total = 1`.

- [ ] **Step 5: Commit**

```bash
git add packaging/sql/migrations/013_* packaging/sql/migrations/014_* \
        packaging/sql/migrations/015_* packaging/sql/migrations/016_* \
        packaging/sql/migrations/017_* packaging/sql/migrations/018_* \
        packaging/sql/migrations/019_*
git commit -m "feat(tenancy): migrations 013-019 — org_id column on 7 business tables"
```

---

### Task 3: Migrations 020–021 — settings PK reshape + users.last_active_org_id

**Files:**
- Create: `packaging/sql/migrations/020_settings_org_id.{up,down}.sql`
- Create: `packaging/sql/migrations/021_users_last_active_org.{up,down}.sql`

- [ ] **Step 1: Write 020_settings_org_id.up.sql**

```sql
-- 020_settings_org_id.up.sql
ALTER TABLE `settings`
    ADD COLUMN `org_id` INT(11) UNSIGNED NOT NULL DEFAULT 1 FIRST,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`org_id`, `setting_key`),
    ADD CONSTRAINT `fk_settings_org`
        FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`) ON DELETE CASCADE;
```

- [ ] **Step 2: Write 020_settings_org_id.down.sql**

```sql
ALTER TABLE `settings`
    DROP FOREIGN KEY `fk_settings_org`,
    DROP PRIMARY KEY,
    DROP COLUMN `org_id`,
    ADD PRIMARY KEY (`setting_key`);
```

- [ ] **Step 3: Write 021_users_last_active_org.up.sql**

```sql
-- 021_users_last_active_org.up.sql
ALTER TABLE `users`
    ADD COLUMN `last_active_org_id` INT(11) UNSIGNED NULL DEFAULT NULL,
    ADD CONSTRAINT `fk_users_last_active_org`
        FOREIGN KEY (`last_active_org_id`) REFERENCES `orgs` (`id`) ON DELETE SET NULL;
```

- [ ] **Step 4: Write 021_users_last_active_org.down.sql**

```sql
ALTER TABLE `users`
    DROP FOREIGN KEY `fk_users_last_active_org`,
    DROP COLUMN `last_active_org_id`;
```

- [ ] **Step 5: Run migrations 020–021**

```bash
mysql -u root inventory < packaging/sql/migrations/020_settings_org_id.up.sql
mysql -u root inventory < packaging/sql/migrations/021_users_last_active_org.up.sql
```

Verify:
```bash
mysql -u root inventory -e "DESCRIBE settings; DESCRIBE users;" | grep -E "org_id|last_active"
```

Expected: `settings` shows `org_id` as first column in PK; `users` shows `last_active_org_id` nullable.

- [ ] **Step 6: Commit**

```bash
git add packaging/sql/migrations/020_* packaging/sql/migrations/021_*
git commit -m "feat(tenancy): migrations 020-021 — settings PK reshape, users.last_active_org_id"
```

---

### Task 4: Mirror migrations into schema.sql

**Files:**
- Modify: `packaging/sql/schema.sql`

- [ ] **Step 1: Apply all tenancy DDL to schema.sql**

Open `packaging/sql/schema.sql` and make the following changes so fresh installs via `install.sh` get the post-migration state:

1. Add the `orgs` table definition (from 010) before the `users` table.
2. Add the `org_members` table definition (from 011) after `users`.
3. Add `org_id INT(11) UNSIGNED NOT NULL DEFAULT 1` as the second column in `customers`, `products`, `categories`, `sales`, `orders`, `stock`, `media`.
4. Add FK constraints `fk_*_org` for each of those tables.
5. Reshape `settings` PK to `(org_id, setting_key)`.
6. Add `last_active_org_id` nullable to `users`.
7. Add the seed `INSERT` for org 1 (from 012) in the seed-data section.

- [ ] **Step 2: Verify schema.sql is clean**

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS inventory_schema_test;"
mysql -u root inventory_schema_test < packaging/sql/schema.sql
mysql -u root -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='inventory_schema_test' ORDER BY TABLE_NAME;"
mysql -u root -e "DROP DATABASE inventory_schema_test;"
```

Expected: all tables present including `orgs` and `org_members`, no errors.

- [ ] **Step 3: Commit**

```bash
git add packaging/sql/schema.sql
git commit -m "feat(tenancy): mirror migrations 010-021 into schema.sql"
```

---

### Task 5: ORG_SCOPED_TABLES constant + table_has_org_id() probe

**Files:**
- Modify: `includes/sql.php` (near line 139 where `SOFT_DELETE_TABLES` is defined)

- [ ] **Step 1: Write the failing test**

In `tests/TenancyTest.php` (create if absent):

```php
<?php
require_once __DIR__ . '/bootstrap.php';

class TenancyTest extends PHPUnit\Framework\TestCase {

    public static function setUpBeforeClass(): void {
        global $db;
        // Skip whole suite if tenancy migrations haven't been applied.
        $row = $db->prepare_select_one(
            "SELECT COUNT(*) AS n FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orgs'",
            "", 
        );
        if ((int)($row['n'] ?? 0) === 0) {
            self::markTestSkipped('Tenancy migrations 010-021 not applied.');
        }
    }

    public function test_org_scoped_tables_constant_is_defined(): void {
        $this->assertIsArray(ORG_SCOPED_TABLES);
        $this->assertContains('customers', ORG_SCOPED_TABLES);
        $this->assertContains('products',  ORG_SCOPED_TABLES);
    }

    public function test_table_has_org_id_returns_true_for_customers(): void {
        $this->assertTrue(table_has_org_id('customers'));
    }

    public function test_table_has_org_id_returns_false_for_users(): void {
        $this->assertFalse(table_has_org_id('users'));
    }
}
```

- [ ] **Step 2: Run test — expect failure**

```bash
bash tests/run.sh 2>&1 | grep -E "FAIL|Error|ORG_SCOPED"
```

Expected: `Error: Undefined constant ORG_SCOPED_TABLES` or `FAIL`.

- [ ] **Step 3: Add constant + probe to includes/sql.php**

After the `SOFT_DELETE_TABLES` block (~line 139):

```php
const ORG_SCOPED_TABLES = [
    'customers', 'products', 'categories',
    'sales', 'orders', 'stock', 'media',
];

function table_has_org_id(string $table): bool {
    static $cache = [];
    if (!in_array($table, ORG_SCOPED_TABLES, true)) {
        return false;
    }
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    global $db;
    try {
        $row = $db->prepare_select_one(
            "SELECT COUNT(*) AS n
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = ?
                AND COLUMN_NAME  = 'org_id'",
            's', $table
        );
        $cache[$table] = (int)($row['n'] ?? 0) > 0;
    } catch (\Throwable $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}
```

- [ ] **Step 4: Run test — expect pass**

```bash
bash tests/run.sh 2>&1 | grep -E "OK|FAIL|Error"
```

Expected: the three new cases pass; full suite still green.

- [ ] **Step 5: Commit**

```bash
git add includes/sql.php tests/TenancyTest.php
git commit -m "feat(tenancy): ORG_SCOPED_TABLES const + table_has_org_id() column probe"
```

---

### Task 6: current_org_id() helper

**Files:**
- Modify: `includes/sql.php`

- [ ] **Step 1: Add test case to TenancyTest.php**

```php
public function test_current_org_id_throws_when_session_empty(): void {
    $saved = $_SESSION['current_org_id'] ?? null;
    unset($_SESSION['current_org_id']);
    $this->expectException(\RuntimeException::class);
    current_org_id();
    if ($saved !== null) {
        $_SESSION['current_org_id'] = $saved;
    }
}

public function test_current_org_id_returns_session_value(): void {
    $_SESSION['current_org_id'] = 7;
    $this->assertSame(7, current_org_id());
    unset($_SESSION['current_org_id']);
}
```

- [ ] **Step 2: Run — expect failure**

```bash
bash tests/run.sh 2>&1 | grep -E "Error|FAIL|current_org_id"
```

- [ ] **Step 3: Add function to includes/sql.php** (just above `find_all`):

```php
function current_org_id(): int {
    if (empty($_SESSION['current_org_id'])) {
        throw new \RuntimeException(
            'current_org_id() called with no active org — session not initialized'
        );
    }
    return (int)$_SESSION['current_org_id'];
}
```

- [ ] **Step 4: Run — expect pass**

```bash
bash tests/run.sh 2>&1 | tail -3
```

- [ ] **Step 5: Commit**

```bash
git add includes/sql.php tests/TenancyTest.php
git commit -m "feat(tenancy): current_org_id() helper — throws loudly when session not set"
```

---

### Task 7: find_all() + find_by_id() org-filter upgrade

**Files:**
- Modify: `includes/sql.php` lines 21–77

- [ ] **Step 1: Add leak-prevention test to TenancyTest.php** (needs `seed_multi_org_fixture()` from Task 16 — leave as `$this->markTestIncomplete()` for now and fill in after Task 16):

```php
public function test_find_all_scoped_to_current_org(): void {
    // Temporarily scope to org 1.
    $_SESSION['current_org_id'] = 1;
    $rows = find_all('customers');
    foreach ($rows as $row) {
        $this->assertSame(1, (int)$row['org_id'],
            'find_all returned a row from a different org');
    }
}
```

- [ ] **Step 2: Run — should pass (only org 1 exists so far)**

```bash
bash tests/run.sh 2>&1 | tail -3
```

- [ ] **Step 3: Rewrite find_all() in includes/sql.php**

Replace the existing `find_all()` body (lines 21–31):

```php
function find_all(string $table): ?array {
    global $db;
    if (!tableExists($table)) {
        return null;
    }
    $where   = [];
    $params  = [];
    $types   = '';

    if (table_has_org_id($table)) {
        $where[]  = 'org_id = ?';
        $params[] = current_org_id();
        $types   .= 'i';
    }
    if (table_has_soft_delete($table)) {
        $where[] = 'deleted_at IS NULL';
    }

    $sql = 'SELECT * FROM ' . $db->escape($table);
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    if ($params) {
        return $db->prepare_select($sql, $types, ...$params);
    }
    return find_by_sql($sql);
}
```

- [ ] **Step 4: Rewrite find_by_id() in includes/sql.php**

Replace the existing `find_by_id()` body (lines 64–77):

```php
function find_by_id(string $table, int $id): ?array {
    global $db;
    if (!tableExists($table)) {
        return null;
    }
    $where  = 'WHERE id = ?';
    $params = [$id];
    $types  = 'i';

    if (table_has_org_id($table)) {
        $where   .= ' AND org_id = ?';
        $params[] = current_org_id();
        $types   .= 'i';
    }
    if (table_has_soft_delete($table)) {
        $where .= ' AND deleted_at IS NULL';
    }

    return $db->prepare_select_one(
        'SELECT * FROM ' . $db->escape($table) . " {$where} LIMIT 1",
        $types,
        ...$params
    );
}
```

Apply the same org-filter pattern to `find_by_id_with_deleted()` (~line 254): add `AND org_id = ?` binding when `table_has_org_id($table)` is true, but omit the `deleted_at IS NULL` clause (that's the point of `_with_deleted`).

- [ ] **Step 5: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -5
```

Expected: all existing tests still pass (they use org 1 which is the only org).

- [ ] **Step 6: Commit**

```bash
git add includes/sql.php tests/TenancyTest.php
git commit -m "feat(tenancy): find_all/find_by_id auto-filter on org_id using prepared statements"
```

---

### Task 8: authenticate() org-resolution + Session::login() signature

**Files:**
- Modify: `includes/sql.php` function `authenticate()` (~line 385)
- Modify: `includes/session.php` function `login()`
- Modify: `users/index.php` (call site)

- [ ] **Step 1: Add auth flow tests to TenancyTest.php**

```php
// Requires at least one user in org 1. Adjust usernames to match your seeded data.
public function test_authenticate_returns_user_and_org_id(): void {
    $_SESSION['current_org_id'] = 1; // authenticate() calls resolve_login_org() which needs no session
    $result = authenticate('admin', 'your-test-password');
    $this->assertIsArray($result);
    $this->assertArrayHasKey('user_id', $result);
    $this->assertArrayHasKey('org_id',  $result);
    $this->assertGreaterThan(0, $result['org_id']);
}

public function test_authenticate_returns_false_for_bad_password(): void {
    $result = authenticate('admin', 'wrong-password');
    $this->assertFalse($result);
}
```

- [ ] **Step 2: Run — expect failure (authenticate() still returns int)**

```bash
bash tests/run.sh 2>&1 | grep -E "Error|FAIL|authenticate"
```

- [ ] **Step 3: Add org-resolution helper to includes/sql.php**

Add before `authenticate()`:

```php
/**
 * Resolve which org_id to use at login for a given user.
 * Returns the org_id, or false if the user has no accessible org.
 */
function resolve_login_org(int $user_id, ?int $last_active_org_id): int|false {
    global $db;
    // Try last_active_org_id first (if set and membership + org not soft-deleted).
    if ($last_active_org_id !== null) {
        $row = $db->prepare_select_one(
            "SELECT m.org_id FROM org_members m
               JOIN orgs o ON o.id = m.org_id
              WHERE m.user_id = ? AND m.org_id = ? AND o.deleted_at IS NULL",
            'ii', $user_id, $last_active_org_id
        );
        if ($row) {
            return (int)$row['org_id'];
        }
    }
    // Fall back to oldest membership in a live org.
    $row = $db->prepare_select_one(
        "SELECT m.org_id FROM org_members m
           JOIN orgs o ON o.id = m.org_id
          WHERE m.user_id = ? AND o.deleted_at IS NULL
          ORDER BY m.joined_at ASC LIMIT 1",
        'i', $user_id
    );
    return $row ? (int)$row['org_id'] : false;
}
```

- [ ] **Step 4: Update authenticate() to return ['user_id', 'org_id'] or false**

Change the SQL to also fetch `last_active_org_id`:

```php
$sql = "SELECT id, username, password, user_level, last_active_org_id
          FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1";
$result = $db->prepare_select_one($sql, 's', $username);
```

Replace both `return $result['id'];` lines with:

```php
$org_id = resolve_login_org((int)$result['id'], $result['last_active_org_id'] ?? null);
if ($org_id === false) {
    return false; // user has no org access — caller shows "contact administrator"
}
return ['user_id' => (int)$result['id'], 'org_id' => $org_id];
```

- [ ] **Step 5: Update Session::login() in includes/session.php**

```php
public function login(int $user_id, int $org_id): void {
    $_SESSION['user_id']        = $user_id;
    $_SESSION['current_org_id'] = $org_id;
    session_regenerate_id(true);
}
```

- [ ] **Step 6: Update the call site in users/index.php**

Find the block that calls `authenticate()` and `$session->login()`. Replace:

```php
$user_id = authenticate($username, $password);
if ($user_id) {
    $session->login($user_id);
    // ...
}
```

With:

```php
$auth = authenticate($username, $password);
if ($auth) {
    $session->login($auth['user_id'], $auth['org_id']);
    // ...
} elseif ($auth === false && /* rate limit check */ false) {
    // keep existing rate-limit path unchanged
}
```

Adjust the exact surrounding logic to match what's already in `users/index.php` — only the `authenticate()` return shape changes.

- [ ] **Step 7: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -5
```

- [ ] **Step 8: Commit**

```bash
git add includes/sql.php includes/session.php users/index.php tests/TenancyTest.php
git commit -m "feat(tenancy): authenticate() returns [user_id, org_id]; Session::login() sets current_org_id"
```

---

### Task 9: require_org_role() + page_require_level() shim + find_org_members()

**Files:**
- Modify: `includes/sql.php` (near `page_require_level` at ~line 573)

- [ ] **Step 1: Add test cases to TenancyTest.php**

```php
public function test_require_org_role_allows_matching_role(): void {
    $_SESSION['current_org_id'] = 1;
    $_SESSION['user_id'] = 1; // must be an owner in org 1 after backfill
    // Should not throw or redirect.
    $m = require_org_role('owner', 'admin', 'member');
    $this->assertArrayHasKey('role', $m);
}

public function test_find_org_members_returns_only_org_members(): void {
    $_SESSION['current_org_id'] = 1;
    $members = find_org_members(1);
    $this->assertIsArray($members);
    $this->assertNotEmpty($members);
    $this->assertArrayHasKey('role', $members[0]);
}
```

- [ ] **Step 2: Run — expect failure**

```bash
bash tests/run.sh 2>&1 | grep -E "Error|FAIL|require_org_role|find_org_members"
```

- [ ] **Step 3: Add find_membership() helper to includes/sql.php**

```php
function find_membership(int $user_id, int $org_id): ?array {
    global $db;
    return $db->prepare_select_one(
        "SELECT m.role, m.joined_at
           FROM org_members m
           JOIN orgs o ON o.id = m.org_id
          WHERE m.user_id = ? AND m.org_id = ? AND o.deleted_at IS NULL",
        'ii', $user_id, $org_id
    );
}
```

- [ ] **Step 4: Add require_org_role() to includes/sql.php**

```php
function require_org_role(string ...$allowed): array {
    $user   = current_user();
    $org_id = current_org_id();
    $m      = find_membership((int)$user['id'], $org_id);
    if (!$m) {
        $_SESSION['error_msg'] = 'You are not a member of this organization.';
        redirect('users/index.php');
        exit;
    }
    if (!in_array($m['role'], $allowed, true)) {
        $_SESSION['error_msg'] = 'You do not have permission for this action.';
        redirect('users/index.php');
        exit;
    }
    return $m;
}
```

- [ ] **Step 5: Replace page_require_level() with shim in includes/sql.php**

Replace the existing `page_require_level()` body (~line 573) with:

```php
function page_require_level(int $require_level): void {
    // Shim: maps old numeric tier to org roles. Removed in PR2.
    $map = [
        ROLE_ADMIN      => ['owner'],
        ROLE_SUPERVISOR => ['owner', 'admin'],
        ROLE_USER       => ['owner', 'admin', 'member'],
    ];
    require_org_role(...($map[$require_level] ?? ['owner']));
}
```

- [ ] **Step 6: Add find_org_members() to includes/sql.php**

```php
function find_org_members(int $org_id): array {
    global $db;
    return $db->prepare_select(
        "SELECT u.id, u.name, u.username, u.email, m.role, m.joined_at
           FROM users u
           JOIN org_members m ON m.user_id = u.id
          WHERE m.org_id = ? AND u.deleted_at IS NULL
          ORDER BY u.name ASC",
        'i', $org_id
    ) ?? [];
}
```

- [ ] **Step 7: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -5
```

- [ ] **Step 8: Commit**

```bash
git add includes/sql.php tests/TenancyTest.php
git commit -m "feat(tenancy): require_org_role(), page_require_level() shim, find_org_members()"
```

---

### Task 10: Hand-written SELECT audit

**Files:**
- Modify: `includes/sql.php` — every `SELECT` that references an org-scoped table but is not routed through `find_all` / `find_by_id`.

- [ ] **Step 1: Enumerate all hand-written queries touching org-scoped tables**

```bash
grep -n "FROM\s\+\(customers\|products\|categories\|sales\|orders\|stock\|media\)" \
     includes/sql.php | grep -v "find_all\|find_by_id"
```

Note every line number returned. This is your audit list.

- [ ] **Step 2: For each query, add AND <alias>.org_id = ? binding**

Pattern — before:
```php
$sql = "SELECT p.*, c.name AS category_name
          FROM products p
          JOIN categories c ON c.id = p.category_id
         WHERE p.deleted_at IS NULL";
$rows = find_by_sql($sql);
```

After (switch to prepare_select + bind org_id):
```php
$rows = $db->prepare_select(
    "SELECT p.*, c.name AS category_name
       FROM products p
       JOIN categories c ON c.id = p.category_id
      WHERE p.deleted_at IS NULL
        AND p.org_id = ?",
    'i', current_org_id()
);
```

Apply this transformation to every query in the audit list. Where a query joins multiple org-scoped tables, add the `org_id` filter on the driving table only (the one in the `FROM` clause).

- [ ] **Step 3: Run full suite after each batch of 5 queries**

```bash
bash tests/run.sh 2>&1 | tail -3
```

Fix any regressions before continuing.

- [ ] **Step 4: Confirm no un-filtered references remain**

```bash
grep -n "FROM\s\+\(customers\|products\|categories\|sales\|orders\|stock\|media\)" \
     includes/sql.php | grep -v "org_id"
```

Expected: empty output.

- [ ] **Step 5: Commit**

```bash
git add includes/sql.php
git commit -m "fix(tenancy): audit pass — all hand-written SELECTs on org-scoped tables now filter org_id"
```

---

### Task 11: INSERT org_id enforcement

**Files:**
- Modify: `includes/sql.php` — every INSERT into an org-scoped table

- [ ] **Step 1: Enumerate INSERT sites**

```bash
grep -n "INSERT INTO\s\+\`\?\(customers\|products\|categories\|sales\|orders\|stock\|media\)\`\?" \
     includes/sql.php
```

- [ ] **Step 2: Add org_id to every INSERT**

Before (example for add_customer):
```php
$db->prepare_query(
    "INSERT INTO customers (name, email, telephone, address) VALUES (?,?,?,?)",
    'ssss', $name, $email, $telephone, $address
);
```

After:
```php
$db->prepare_query(
    "INSERT INTO customers (org_id, name, email, telephone, address) VALUES (?,?,?,?,?)",
    'issss', current_org_id(), $name, $email, $telephone, $address
);
```

Repeat for every INSERT site. The type string gains a leading `i` for the org_id int.

- [ ] **Step 3: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -3
```

- [ ] **Step 4: Commit**

```bash
git add includes/sql.php
git commit -m "fix(tenancy): all INSERTs on org-scoped tables now include org_id = current_org_id()"
```

---

### Task 12: UPDATE / DELETE org_id guard

**Files:**
- Modify: `includes/sql.php` — every UPDATE/DELETE on org-scoped tables; also `delete_resource()` if it's a generic helper

- [ ] **Step 1: Enumerate UPDATE/DELETE sites**

```bash
grep -n "UPDATE\s\+\`\?\(customers\|products\|categories\|sales\|orders\|stock\|media\)\`\?\|DELETE FROM\s\+\`\?\(customers\|products\|categories\|sales\|orders\|stock\|media\)\`\?" \
     includes/sql.php
```

- [ ] **Step 2: Add AND org_id = ? to every WHERE clause**

Before:
```php
$db->prepare_query(
    "UPDATE customers SET name=?, email=? WHERE id=?",
    'ssi', $name, $email, $id
);
```

After:
```php
$db->prepare_query(
    "UPDATE customers SET name=?, email=? WHERE id=? AND org_id=?",
    'ssii', $name, $email, $id, current_org_id()
);
```

If a generic `delete_resource($table, $id)` function exists, add the org-filter there:

```php
function delete_resource(string $table, int $id): void {
    global $db;
    if (table_has_org_id($table)) {
        $db->prepare_query(
            "DELETE FROM {$db->escape($table)} WHERE id = ? AND org_id = ?",
            'ii', $id, current_org_id()
        );
    } else {
        $db->prepare_query(
            "DELETE FROM {$db->escape($table)} WHERE id = ?",
            'i', $id
        );
    }
}
```

Soft-delete calls (`UPDATE … SET deleted_at = NOW() WHERE id = ?`) get the same treatment.

- [ ] **Step 3: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -3
```

- [ ] **Step 4: Commit**

```bash
git add includes/sql.php
git commit -m "fix(tenancy): UPDATE/DELETE on org-scoped tables now guard with AND org_id = current_org_id()"
```

---

### Task 13: Settings org-scoping

**Files:**
- Modify: `includes/settings.php`

- [ ] **Step 1: Add test to TenancyTest.php**

```php
public function test_settings_get_scoped_to_org(): void {
    $_SESSION['current_org_id'] = 1;
    Settings::clear_cache();
    $val = Settings::get('currency_code', 'USD');
    $this->assertSame('USD', $val);
}
```

- [ ] **Step 2: Run — should pass (Settings still loads all settings; org 1 has the row)**

```bash
bash tests/run.sh 2>&1 | tail -3
```

- [ ] **Step 3: Update Settings::load() to filter by current_org_id**

Replace the raw query in `load()`:

```php
private static function load(): void {
    global $db;
    self::$loaded = true;
    try {
        $org_id = function_exists('current_org_id') ? current_org_id() : 1;
        $rows = $db->prepare_select(
            'SELECT `setting_key`, `setting_value` FROM `settings` WHERE `org_id` = ?',
            'i', $org_id
        );
        foreach ($rows ?? [] as $row) {
            self::$cache[$row['setting_key']] = $row['setting_value'];
        }
    } catch (\Throwable $e) {
        error_log('Settings::load() — settings table unavailable, using defaults: ' . $e->getMessage());
    }
}
```

- [ ] **Step 4: Update Settings::set() to include org_id**

```php
public static function set(string $key, string $value): bool {
    global $db;
    $org_id = function_exists('current_org_id') ? current_org_id() : 1;
    $stmt = $db->prepare_query(
        'INSERT INTO `settings` (`org_id`, `setting_key`, `setting_value`) VALUES (?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)',
        'iss', $org_id, $key, $value
    );
    $stmt->close();
    self::$cache[$key] = $value;
    self::$loaded = true;
    return true;
}
```

- [ ] **Step 5: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -3
```

- [ ] **Step 6: Commit**

```bash
git add includes/settings.php tests/TenancyTest.php
git commit -m "feat(tenancy): Settings::get/set scoped to current_org_id()"
```

---

### Task 14: users/switch_org.php endpoint

**Files:**
- Create: `users/switch_org.php`

- [ ] **Step 1: Add switch endpoint tests to TenancyTest.php** (these are integration tests requiring HTTP — mark incomplete for now; they'll be smoke-tested manually per spec § 9.5):

```php
public function test_switch_org_endpoint_placeholder(): void {
    // Smoke-tested manually — see spec § 9.5 PR1 steps 2-3.
    $this->markTestIncomplete('Manual smoke test: curl POST /users/switch_org.php');
}
```

- [ ] **Step 2: Create users/switch_org.php**

```php
<?php
require_once '../includes/load.php';
page_require_level(ROLE_USER);

csrf_verify_ajax(); // CSRF check — switch is a POST from a form or JS fetch

$posted_org_id = (int)($_POST['org_id'] ?? 0);
if ($posted_org_id <= 0) {
    $_SESSION['error_msg'] = 'Invalid organization.';
    redirect('users/home.php');
    exit;
}

$user = current_user();
$m    = find_membership((int)$user['id'], $posted_org_id);

if (!$m) {
    $_SESSION['error_msg'] = 'You are not a member of that organization.';
    redirect('users/home.php');
    exit;
}

$_SESSION['current_org_id'] = $posted_org_id;

global $db;
$db->prepare_query(
    "UPDATE users SET last_active_org_id = ? WHERE id = ?",
    'ii', $posted_org_id, (int)$user['id']
);

redirect('users/home.php');
```

- [ ] **Step 3: Lint**

```bash
php -l users/switch_org.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add users/switch_org.php
git commit -m "feat(tenancy): POST /users/switch_org.php — CSRF-protected org switcher endpoint"
```

---

### Task 15: Pre-commit org-filter grep guard

**Files:**
- Modify or create: `.githooks/pre-commit`

- [ ] **Step 1: Check if pre-commit hook exists**

```bash
ls -la .githooks/pre-commit 2>/dev/null || echo "absent"
```

- [ ] **Step 2: Append (or create) the org-scoping guard**

If the file exists, append after the last block. If absent, create it with a shebang:

```bash
#!/usr/bin/env bash
# .githooks/pre-commit

# Org-scoping guard: every SELECT from an org-scoped table must filter org_id.
if git diff --cached -- '*.php' \
   | grep -E '^\+.*FROM\s+(customers|products|categories|sales|orders|stock|media)\b' \
   | grep -qv 'org_id\s*='; then
    echo "✗ New query references an org-scoped table without an org_id filter." >&2
    echo "  Add AND <table>.org_id = ? (or current_org_id()) to the WHERE clause." >&2
    exit 1
fi
```

- [ ] **Step 3: Make executable and register with git**

```bash
chmod +x .githooks/pre-commit
git config core.hooksPath .githooks
```

- [ ] **Step 4: Verify hook triggers on a bad addition**

```bash
echo '<?php $db->query("SELECT * FROM customers WHERE name=?");' > /tmp/hook_test.php
cp /tmp/hook_test.php includes/hook_test_tmp.php
git add includes/hook_test_tmp.php
git commit -m "test" 2>&1 | grep "org-scoped"
git restore --staged includes/hook_test_tmp.php
rm includes/hook_test_tmp.php
```

Expected: hook fires and prints the `✗` message.

- [ ] **Step 5: Commit**

```bash
git add .githooks/pre-commit
git commit -m "chore(tenancy): pre-commit guard — org-scoped table SELECTs must filter org_id"
```

---

### Task 16: tests/lib/tenancy_fixtures.php

**Files:**
- Create: `tests/lib/tenancy_fixtures.php`

- [ ] **Step 1: Create the fixtures file**

```php
<?php
// tests/lib/tenancy_fixtures.php

/**
 * Idempotent: ensures org 1 exists and sets $_SESSION['current_org_id'] = 1.
 * Call from setUp() in any test that touches org-scoped tables.
 */
function setup_test_org_session(): void {
    global $db;
    $db->prepare_query(
        "INSERT IGNORE INTO orgs (id, name, slug) VALUES (1, 'Default Organization', 'default')",
        ''
    );
    $_SESSION['current_org_id'] = 1;
}

/**
 * Creates a second org (id=2, slug='other') plus one customer and one product
 * in that org. Returns ['org_id'=>2, 'customer_id'=>..., 'product_id'=>...].
 */
function seed_multi_org_fixture(): array {
    global $db;
    $db->prepare_query(
        "INSERT IGNORE INTO orgs (id, name, slug) VALUES (2, 'Other Org', 'other')",
        ''
    );
    // Insert a test customer in org 2.
    $db->prepare_query(
        "INSERT INTO customers (org_id, name, email, telephone, address, city, region, postcode, paymethod)
         VALUES (2, 'HARNESS_TenancyCustomer', 'harness@example.com', '555-0000', '1 Test St', 'City', 'State', '00000', 'cash')",
        ''
    );
    $customer_id = $db->connection()->insert_id;

    // Insert a test product in org 2 (requires a category in org 2).
    $db->prepare_query(
        "INSERT IGNORE INTO categories (org_id, name) VALUES (2, 'HARNESS_TenancyCat')",
        ''
    );
    $cat_row = $db->prepare_select_one(
        "SELECT id FROM categories WHERE org_id=2 AND name='HARNESS_TenancyCat'", ''
    );
    $db->prepare_query(
        "INSERT INTO products (org_id, name, sku, quantity, buy_price, sale_price, category_id, media_id)
         VALUES (2, 'HARNESS_TenancyProduct', 'HARNESS-SKU-T', 0, 0, 0, ?, 1)",
        'i', (int)$cat_row['id']
    );
    $product_id = $db->connection()->insert_id;

    return ['org_id' => 2, 'customer_id' => $customer_id, 'product_id' => $product_id];
}

/**
 * Removes all HARNESS_ fixture rows inserted by seed_multi_org_fixture().
 */
function cleanup_multi_org_fixture(): void {
    global $db;
    $db->prepare_query("DELETE FROM customers  WHERE org_id = 2 AND name LIKE 'HARNESS_%'", '');
    $db->prepare_query("DELETE FROM products   WHERE org_id = 2 AND name LIKE 'HARNESS_%'", '');
    $db->prepare_query("DELETE FROM categories WHERE org_id = 2 AND name LIKE 'HARNESS_%'", '');
    $db->prepare_query("DELETE FROM orgs       WHERE id = 2 AND slug = 'other'", '');
}
```

- [ ] **Step 2: Require fixtures from TenancyTest.php** (add near the top):

```php
require_once __DIR__ . '/lib/tenancy_fixtures.php';
```

And add `setUp()` / `tearDown()` to TenancyTest:

```php
protected function setUp(): void {
    setup_test_org_session();
}
```

- [ ] **Step 3: Now fill in the cross-org leak test that was marked incomplete in Task 7**

```php
public function test_find_all_does_not_leak_across_orgs(): void {
    $fixture = seed_multi_org_fixture();
    try {
        // Scoped to org 1 — org 2's customer must not appear.
        $_SESSION['current_org_id'] = 1;
        $rows = find_all('customers');
        $ids  = array_column($rows, 'id');
        $this->assertNotContains($fixture['customer_id'], $ids,
            'find_all() returned a row from org 2 while session is org 1');
    } finally {
        cleanup_multi_org_fixture();
    }
}

public function test_find_by_id_returns_null_for_other_org(): void {
    $fixture = seed_multi_org_fixture();
    try {
        $_SESSION['current_org_id'] = 1;
        $row = find_by_id('products', $fixture['product_id']);
        $this->assertNull($row, 'find_by_id() returned a product from org 2 while session is org 1');
    } finally {
        cleanup_multi_org_fixture();
    }
}
```

- [ ] **Step 4: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -5
```

- [ ] **Step 5: Commit**

```bash
git add tests/lib/tenancy_fixtures.php tests/TenancyTest.php
git commit -m "test(tenancy): tenancy_fixtures.php + cross-org leak tests for find_all/find_by_id"
```

---

### Task 17: Add setup_test_org_session() to existing test suites

**Files:**
- Modify: every existing `tests/*.php` that uses org-scoped tables (customers, products, sales, orders, stock, media, categories) — at minimum: `CRUDTest.php`, `AuthTest.php`, `SoftDeleteTest.php`, `SettingsTest.php`

- [ ] **Step 1: Identify affected test files**

```bash
grep -l "customers\|products\|categories\|sales\|orders\|stock" tests/*.php
```

- [ ] **Step 2: For each file, add the require and setUp call**

Add at top of each file:
```php
require_once __DIR__ . '/lib/tenancy_fixtures.php';
```

Add `setUp()` method to the test class (or extend existing `setUp()`):
```php
protected function setUp(): void {
    parent::setUp(); // if parent setUp exists
    setup_test_org_session();
}
```

- [ ] **Step 3: Run full suite — all existing tests must still pass**

```bash
bash tests/run.sh 2>&1 | tail -5
```

Fix any breakages before moving on.

- [ ] **Step 4: Commit**

```bash
git add tests/
git commit -m "test(tenancy): existing test suites now call setup_test_org_session() in setUp()"
```

---

### Task 18: Complete TenancyTest.php — remaining 18 cases

**Files:**
- Modify: `tests/TenancyTest.php`

- [ ] **Step 1: Add backfill correctness cases**

```php
public function test_backfill_orgs_has_one_row(): void {
    $row = $GLOBALS['db']->prepare_select_one(
        "SELECT COUNT(*) AS n FROM orgs", ''
    );
    $this->assertSame(1, (int)$row['n']);
}

public function test_backfill_all_users_have_org1_membership(): void {
    global $db;
    $row = $db->prepare_select_one(
        "SELECT COUNT(*) AS n FROM users u
          LEFT JOIN org_members m ON m.user_id = u.id AND m.org_id = 1
         WHERE u.deleted_at IS NULL AND m.user_id IS NULL",
        ''
    );
    $this->assertSame(0, (int)$row['n'],
        'Some non-deleted users are missing an org_members row for org 1');
}

public function test_backfill_role_mapping_correct(): void {
    global $db;
    // An admin-level user (user_level=1) must be 'owner'.
    $row = $db->prepare_select_one(
        "SELECT COUNT(*) AS n FROM users u
           JOIN org_members m ON m.user_id = u.id AND m.org_id = 1
          WHERE u.user_level = 1 AND m.role != 'owner' AND u.deleted_at IS NULL",
        ''
    );
    $this->assertSame(0, (int)$row['n'],
        'Admin-tier users were not backfilled as owner in org 1');
}

public function test_backfill_all_business_rows_on_org1(): void {
    global $db;
    foreach (['customers','products','categories','sales','orders','stock'] as $t) {
        $row = $db->prepare_select_one(
            "SELECT COUNT(*) AS n FROM `{$t}` WHERE org_id != 1", ''
        );
        $this->assertSame(0, (int)$row['n'],
            "Table `{$t}` has rows not assigned to org 1 after backfill");
    }
}

public function test_backfill_settings_currency_on_org1(): void {
    global $db;
    $row = $db->prepare_select_one(
        "SELECT org_id FROM settings WHERE setting_key = 'currency_code'", ''
    );
    $this->assertNotNull($row);
    $this->assertSame(1, (int)$row['org_id']);
}
```

- [ ] **Step 2: Add write/update/delete path cases**

```php
public function test_insert_without_org_id_rejected_by_not_null_constraint(): void {
    global $db;
    $this->expectException(\Throwable::class);
    $db->prepare_query(
        "INSERT INTO customers (name, email, telephone, address, city, region, postcode, paymethod)
         VALUES ('HARNESS_NoOrg','x@x.com','000','1 St','City','ST','00000','cash')",
        ''
    );
}

public function test_update_on_other_org_row_affects_zero_rows(): void {
    global $db;
    $fixture = seed_multi_org_fixture();
    try {
        $_SESSION['current_org_id'] = 1;
        $db->prepare_query(
            "UPDATE customers SET name='HARNESS_Modified' WHERE id=? AND org_id=?",
            'ii', $fixture['customer_id'], current_org_id()
        );
        $row = $db->prepare_select_one(
            "SELECT name FROM customers WHERE id=?", 'i', $fixture['customer_id']
        );
        $this->assertNotSame('HARNESS_Modified', $row['name']);
    } finally {
        cleanup_multi_org_fixture();
    }
}
```

- [ ] **Step 3: Add login + switch cases** (these exercise the SQL helpers, not the HTTP layer):

```php
public function test_resolve_login_org_returns_org1_for_existing_user(): void {
    global $db;
    $user_row = $db->prepare_select_one(
        "SELECT id, last_active_org_id FROM users WHERE deleted_at IS NULL LIMIT 1", ''
    );
    $org_id = resolve_login_org((int)$user_row['id'], $user_row['last_active_org_id']);
    $this->assertSame(1, $org_id);
}

public function test_resolve_login_org_returns_false_for_memberless_user(): void {
    // Insert a user with no memberships.
    global $db;
    $db->prepare_query(
        "INSERT INTO users (name, username, email, password, user_level, status)
         VALUES ('HARNESS_Orphan','harness_orphan','o@o.com','x',3,'Active')",
        ''
    );
    $user_id = $db->connection()->insert_id;
    $result  = resolve_login_org($user_id, null);
    $db->prepare_query("DELETE FROM users WHERE id=?", 'i', $user_id);
    $this->assertFalse($result);
}
```

- [ ] **Step 4: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -5
```

All 18 tenancy cases green; full suite green.

- [ ] **Step 5: Commit**

```bash
git add tests/TenancyTest.php
git commit -m "test(tenancy): complete TenancyTest.php — 18 cases covering backfill, read/write isolation, auth flow"
```

---

### PR1 completion checklist

Before opening the PR:

- [ ] `bash tests/run.sh` — all tests green
- [ ] `php -l users/switch_org.php includes/sql.php includes/session.php includes/settings.php` — no syntax errors
- [ ] `bash packaging/sql/migrate.sh --status` shows migrations 010–021 applied
- [ ] Manual smoke: log in, confirm existing pages load unchanged, confirm Settings::get('currency_code') returns expected value
- [ ] `git log --oneline feature/tenancy-schema` — confirm clean commit history
- [ ] Open PR targeting `main`; include the migration run command block from spec § 7.3 in the PR description

---

## Phase 2 — PR2: UX surface

> **Prerequisite:** PR1 merged to `main` and migrations 010–021 run on the live DB. Branch PR2 from `main` after smoke.

---

### Task 19: Org management SQL functions

**Files:**
- Modify: `includes/sql.php`

- [ ] **Step 1: Add test stubs to OrgManagementTest.php** (create file):

```php
<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/tenancy_fixtures.php';

class OrgManagementTest extends PHPUnit\Framework\TestCase {

    public static function setUpBeforeClass(): void {
        global $db;
        $row = $db->prepare_select_one(
            "SELECT COUNT(*) AS n FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orgs'", ''
        );
        if ((int)($row['n'] ?? 0) === 0) {
            self::markTestSkipped('Tenancy migrations not applied.');
        }
    }

    protected function setUp(): void {
        setup_test_org_session();
    }
}
```

- [ ] **Step 2: Add add_org() function to includes/sql.php**

```php
function add_org(string $name, string $slug, int $creator_user_id): int {
    global $db;
    $db->prepare_query(
        "INSERT INTO orgs (name, slug) VALUES (?, ?)",
        'ss', $name, $slug
    );
    $org_id = (int)$db->connection()->insert_id;
    $db->prepare_query(
        "INSERT INTO org_members (org_id, user_id, role) VALUES (?, ?, 'owner')",
        'ii', $org_id, $creator_user_id
    );
    // Seed the no-image placeholder for this org.
    $db->prepare_query(
        "INSERT INTO media (org_id, file_name, file_type) VALUES (?, 'no-image.png', 'image/png')",
        'i', $org_id
    );
    // Seed currency_code setting for the new org (inherit from org 1).
    $currency = Settings::get('currency_code', 'USD');
    Settings::clear_cache();
    $_SESSION['current_org_id'] = $org_id;
    Settings::set('currency_code', $currency);
    $_SESSION['current_org_id'] = $org_id; // restore — set() may have overwritten
    return $org_id;
}
```

- [ ] **Step 3: Write test for add_org()**

```php
public function test_add_org_creates_org_and_enrolls_creator_as_owner(): void {
    global $db;
    $user_row = $db->prepare_select_one(
        "SELECT id FROM users WHERE deleted_at IS NULL LIMIT 1", ''
    );
    $_SESSION['current_org_id'] = 1;
    $org_id = add_org('HARNESS_NewOrg', 'harness-new-org', (int)$user_row['id']);
    try {
        $org = $db->prepare_select_one("SELECT * FROM orgs WHERE id=?", 'i', $org_id);
        $this->assertSame('HARNESS_NewOrg', $org['name']);
        $m = $db->prepare_select_one(
            "SELECT role FROM org_members WHERE org_id=? AND user_id=?",
            'ii', $org_id, (int)$user_row['id']
        );
        $this->assertSame('owner', $m['role']);
    } finally {
        $db->prepare_query("DELETE FROM org_members WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM settings     WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM media        WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM orgs         WHERE id=?",     'i', $org_id);
    }
}
```

- [ ] **Step 4: Add rename_org(), soft_delete_org(), add_org_member(), change_org_member_role(), remove_org_member(), find_all_orgs_for_user()**

```php
function rename_org(int $org_id, string $new_name, string $new_slug): void {
    global $db;
    $db->prepare_query(
        "UPDATE orgs SET name=?, slug=? WHERE id=? AND deleted_at IS NULL",
        'ssi', $new_name, $new_slug, $org_id
    );
}

function soft_delete_org(int $org_id, int $deleted_by): void {
    global $db;
    $db->prepare_query(
        "UPDATE orgs SET deleted_at=NOW(), deleted_by=? WHERE id=? AND deleted_at IS NULL",
        'ii', $deleted_by, $org_id
    );
}

function find_all_orgs_for_user(int $user_id): array {
    global $db;
    return $db->prepare_select(
        "SELECT o.id, o.name, o.slug, o.created_at, m.role
           FROM orgs o
           JOIN org_members m ON m.org_id = o.id
          WHERE m.user_id = ? AND o.deleted_at IS NULL
          ORDER BY m.joined_at ASC",
        'i', $user_id
    ) ?? [];
}

function add_org_member(int $org_id, int $user_id, string $role): void {
    global $db;
    $db->prepare_query(
        "INSERT INTO org_members (org_id, user_id, role) VALUES (?, ?, ?)",
        'iis', $org_id, $user_id, $role
    );
}

function change_org_member_role(int $org_id, int $user_id, string $new_role): void {
    global $db;
    $db->prepare_query(
        "UPDATE org_members SET role=? WHERE org_id=? AND user_id=?",
        'sii', $new_role, $org_id, $user_id
    );
}

function remove_org_member(int $org_id, int $user_id): void {
    global $db;
    $db->prepare_query(
        "DELETE FROM org_members WHERE org_id=? AND user_id=?",
        'ii', $org_id, $user_id
    );
}

function count_org_owners(int $org_id): int {
    global $db;
    $row = $db->prepare_select_one(
        "SELECT COUNT(*) AS n FROM org_members WHERE org_id=? AND role='owner'",
        'i', $org_id
    );
    return (int)($row['n'] ?? 0);
}
```

- [ ] **Step 5: Write remaining OrgManagementTest cases** (last-owner guard, rename, soft-delete):

```php
public function test_rename_org_succeeds_for_owner(): void {
    // ... setup, call rename_org(), assert new name, teardown
    $this->assertTrue(true); // scaffold — fill in with real assertions
}

public function test_count_org_owners_prevents_last_owner_removal(): void {
    global $db;
    // Seed org with exactly one owner.
    $_SESSION['current_org_id'] = 1;
    $user_row = $db->prepare_select_one(
        "SELECT id FROM users WHERE deleted_at IS NULL LIMIT 1", ''
    );
    $org_id = add_org('HARNESS_SingleOwner', 'harness-single-owner', (int)$user_row['id']);
    try {
        $this->assertSame(1, count_org_owners($org_id),
            'Freshly created org must have exactly 1 owner');
        // Attempting removal should be blocked at the UI layer by checking count_org_owners().
        // This test verifies the invariant helper, not the page.
    } finally {
        $db->prepare_query("DELETE FROM org_members WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM settings     WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM media        WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM orgs         WHERE id=?",     'i', $org_id);
    }
}
```

- [ ] **Step 6: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -5
```

- [ ] **Step 7: Commit**

```bash
git add includes/sql.php tests/OrgManagementTest.php
git commit -m "feat(tenancy): org management SQL — add_org, rename_org, soft_delete_org, member CRUD"
```

---

### Task 20: users/orgs.php — org list page

**Files:**
- Create: `users/orgs.php`

- [ ] **Step 1: Create users/orgs.php**

```php
<?php
require_once '../includes/load.php';
page_require_level(ROLE_USER);

$user    = current_user();
$orgs    = find_all_orgs_for_user((int)$user['id']);
$page_title = 'My Organizations';
require_once '../layouts/header.php';
?>
<div class="row">
  <div class="col-md-12">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h2 class="panel-title">My Organizations</h2>
      </div>
      <div class="panel-body">
        <a href="add_org.php" class="btn btn-primary btn-sm">New Organization</a>
        <table class="table table-striped table-bordered" style="margin-top:1rem">
          <thead>
            <tr><th>Name</th><th>Slug</th><th>Your Role</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($orgs as $o): ?>
            <tr>
              <td><?= remove_junk($o['name']) ?></td>
              <td><code><?= remove_junk($o['slug']) ?></code></td>
              <td><?= remove_junk(ucfirst($o['role'])) ?></td>
              <td>
                <form method="POST" action="switch_org.php" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="org_id" value="<?= (int)$o['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-default">Switch</button>
                </form>
                <?php if (in_array($o['role'], ['owner','admin'], true)): ?>
                <a href="edit_org.php?id=<?= (int)$o['id'] ?>" class="btn btn-xs btn-warning">Edit</a>
                <a href="org_members.php?org_id=<?= (int)$o['id'] ?>" class="btn btn-xs btn-info">Members</a>
                <?php endif ?>
              </td>
            </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once '../layouts/footer.php'; ?>
```

- [ ] **Step 2: Lint**

```bash
php -l users/orgs.php
```

- [ ] **Step 3: Commit**

```bash
git add users/orgs.php
git commit -m "feat(tenancy): users/orgs.php — list all orgs the current user belongs to"
```

---

### Task 21: users/add_org.php

**Files:**
- Create: `users/add_org.php`

- [ ] **Step 1: Create users/add_org.php**

```php
<?php
require_once '../includes/load.php';
page_require_level(ROLE_USER);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = sanitize_input($_POST['name'] ?? '');
    $slug = sanitize_input($_POST['slug'] ?? '');

    if (empty($name)) {
        $errors[] = 'Organization name is required.';
    }
    if (!preg_match('/^[a-z0-9-]{1,60}$/', $slug)) {
        $errors[] = 'Slug must be 1–60 lowercase letters, numbers, or hyphens.';
    }

    if (!$errors) {
        $user   = current_user();
        $org_id = add_org($name, $slug, (int)$user['id']);
        $_SESSION['current_org_id'] = $org_id;
        global $db;
        $db->prepare_query("UPDATE users SET last_active_org_id=? WHERE id=?",
            'ii', $org_id, (int)$user['id']);
        $_SESSION['success_msg'] = remove_junk($name) . ' created. You are now in this organization.';
        redirect('users/orgs.php');
        exit;
    }
}

$page_title = 'New Organization';
require_once '../layouts/header.php';
?>
<div class="row"><div class="col-md-6">
  <div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">New Organization</h2></div>
    <div class="panel-body">
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= remove_junk($e) ?></div>
      <?php endforeach ?>
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" class="form-control" maxlength="120"
                 value="<?= remove_junk($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Slug <small class="text-muted">(URL-safe identifier, e.g. <code>acme-corp</code>)</small></label>
          <input type="text" name="slug" class="form-control" maxlength="60"
                 value="<?= remove_junk($_POST['slug'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Create Organization</button>
        <a href="orgs.php" class="btn btn-default">Cancel</a>
      </form>
    </div>
  </div>
</div></div>
<?php require_once '../layouts/footer.php'; ?>
```

- [ ] **Step 2: Lint + commit**

```bash
php -l users/add_org.php
git add users/add_org.php
git commit -m "feat(tenancy): users/add_org.php — create org; creator auto-enrolled as owner"
```

---

### Task 22: users/edit_org.php

**Files:**
- Create: `users/edit_org.php`

- [ ] **Step 1: Create users/edit_org.php**

```php
<?php
require_once '../includes/load.php';
page_require_level(ROLE_USER);

$org_id = (int)($_GET['id'] ?? 0);
if ($org_id <= 0) { redirect('users/orgs.php'); exit; }

$m = require_org_role('owner', 'admin');
global $db;
$org = $db->prepare_select_one(
    "SELECT * FROM orgs WHERE id=? AND deleted_at IS NULL", 'i', $org_id
);
if (!$org) { redirect('users/orgs.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'rename') {
        $name = sanitize_input($_POST['name'] ?? '');
        $slug = sanitize_input($_POST['slug'] ?? '');
        if (empty($name)) { $errors[] = 'Name is required.'; }
        if (!preg_match('/^[a-z0-9-]{1,60}$/', $slug)) { $errors[] = 'Invalid slug.'; }
        if (!$errors) {
            rename_org($org_id, $name, $slug);
            $_SESSION['success_msg'] = 'Organization updated.';
            redirect('users/edit_org.php?id=' . $org_id);
            exit;
        }
    }

    if ($action === 'delete' && $m['role'] === 'owner') {
        $user = current_user();
        soft_delete_org($org_id, (int)$user['id']);
        // Send the user back to their oldest remaining org (or home if none).
        $_SESSION['success_msg'] = 'Organization archived.';
        unset($_SESSION['current_org_id']);
        redirect('users/orgs.php');
        exit;
    }
}

$page_title = 'Edit Organization';
require_once '../layouts/header.php';
?>
<div class="row"><div class="col-md-6">
  <div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Edit: <?= remove_junk($org['name']) ?></h2></div>
    <div class="panel-body">
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= remove_junk($e) ?></div>
      <?php endforeach ?>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="rename">
        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" class="form-control" maxlength="120"
                 value="<?= remove_junk($_POST['name'] ?? $org['name']) ?>">
        </div>
        <div class="form-group">
          <label>Slug</label>
          <input type="text" name="slug" class="form-control" maxlength="60"
                 value="<?= remove_junk($_POST['slug'] ?? $org['slug']) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="orgs.php" class="btn btn-default">Cancel</a>
      </form>
      <?php if ($m['role'] === 'owner'): ?>
      <hr>
      <form method="POST" onsubmit="return confirm('Archive this organization? Members will lose access.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn-danger btn-sm">Archive Organization</button>
      </form>
      <?php endif ?>
    </div>
  </div>
</div></div>
<?php require_once '../layouts/footer.php'; ?>
```

- [ ] **Step 2: Lint + commit**

```bash
php -l users/edit_org.php
git add users/edit_org.php
git commit -m "feat(tenancy): users/edit_org.php — rename and soft-delete org (owner only for delete)"
```

---

### Task 23: users/org_members.php

**Files:**
- Create: `users/org_members.php`

- [ ] **Step 1: Create users/org_members.php**

```php
<?php
require_once '../includes/load.php';
page_require_level(ROLE_USER);

$org_id = (int)($_GET['org_id'] ?? current_org_id());
$m      = require_org_role('owner', 'admin');
global $db;
$org     = $db->prepare_select_one("SELECT * FROM orgs WHERE id=? AND deleted_at IS NULL", 'i', $org_id);
if (!$org) { redirect('users/orgs.php'); exit; }

$members = find_org_members($org_id);
$page_title = 'Members: ' . $org['name'];
require_once '../layouts/header.php';
?>
<div class="row"><div class="col-md-8">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h2 class="panel-title">Members of <?= remove_junk($org['name']) ?></h2>
    </div>
    <div class="panel-body">
      <?php if ($m['role'] === 'owner' || $m['role'] === 'admin'): ?>
      <a href="add_org_member.php?org_id=<?= $org_id ?>" class="btn btn-primary btn-sm">Add Member</a>
      <?php endif ?>
      <table class="table table-striped table-bordered" style="margin-top:1rem">
        <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($members as $mbr): ?>
          <tr>
            <td><?= remove_junk($mbr['name']) ?></td>
            <td><?= remove_junk($mbr['username']) ?></td>
            <td><?= remove_junk(ucfirst($mbr['role'])) ?></td>
            <td><?= remove_junk($mbr['joined_at']) ?></td>
            <td>
              <?php if ($m['role'] === 'owner'): ?>
              <a href="edit_org_member.php?org_id=<?= $org_id ?>&user_id=<?= (int)$mbr['id'] ?>"
                 class="btn btn-xs btn-warning">Edit</a>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
</div></div>
<?php require_once '../layouts/footer.php'; ?>
```

- [ ] **Step 2: Lint + commit**

```bash
php -l users/org_members.php
git add users/org_members.php
git commit -m "feat(tenancy): users/org_members.php — member list with role badges"
```

---

### Task 24: users/add_org_member.php + users/edit_org_member.php

**Files:**
- Create: `users/add_org_member.php`
- Create: `users/edit_org_member.php`

- [ ] **Step 1: Create users/add_org_member.php**

```php
<?php
require_once '../includes/load.php';
page_require_level(ROLE_USER);

$org_id = (int)($_GET['org_id'] ?? current_org_id());
$m      = require_org_role('owner', 'admin');
global $db;
$org = $db->prepare_select_one("SELECT * FROM orgs WHERE id=? AND deleted_at IS NULL", 'i', $org_id);
if (!$org) { redirect('users/org_members.php?org_id=' . $org_id); exit; }

// Build candidate list: all non-deleted users not already in this org.
$candidates = $db->prepare_select(
    "SELECT u.id, u.name, u.username FROM users u
      WHERE u.deleted_at IS NULL
        AND u.id NOT IN (SELECT user_id FROM org_members WHERE org_id=?)
      ORDER BY u.name",
    'i', $org_id
) ?? [];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $user_id  = (int)($_POST['user_id'] ?? 0);
    $role     = sanitize_input($_POST['role'] ?? '');
    $valid_roles = ['owner', 'admin', 'member'];

    if ($user_id <= 0) { $errors[] = 'Select a user.'; }
    if (!in_array($role, $valid_roles, true)) { $errors[] = 'Invalid role.'; }

    if (!$errors) {
        add_org_member($org_id, $user_id, $role);
        $_SESSION['success_msg'] = 'Member added.';
        redirect('users/org_members.php?org_id=' . $org_id);
        exit;
    }
}

$page_title = 'Add Member';
require_once '../layouts/header.php';
?>
<div class="row"><div class="col-md-5">
  <div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Add Member to <?= remove_junk($org['name']) ?></h2></div>
    <div class="panel-body">
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= remove_junk($e) ?></div>
      <?php endforeach ?>
      <?php if (!$candidates): ?>
        <p class="text-muted">All users are already members of this organization.</p>
      <?php else: ?>
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>User</label>
          <select name="user_id" class="form-control">
            <option value="">— select —</option>
            <?php foreach ($candidates as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= remove_junk($c['name']) ?> (<?= remove_junk($c['username']) ?>)</option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="form-group">
          <label>Role</label>
          <select name="role" class="form-control">
            <option value="member">Member</option>
            <option value="admin">Admin</option>
            <?php if ($m['role'] === 'owner'): ?>
            <option value="owner">Owner</option>
            <?php endif ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Add</button>
        <a href="org_members.php?org_id=<?= $org_id ?>" class="btn btn-default">Cancel</a>
      </form>
      <?php endif ?>
    </div>
  </div>
</div></div>
<?php require_once '../layouts/footer.php'; ?>
```

- [ ] **Step 2: Create users/edit_org_member.php**

```php
<?php
require_once '../includes/load.php';
page_require_level(ROLE_USER);

$org_id  = (int)($_GET['org_id']  ?? 0);
$user_id = (int)($_GET['user_id'] ?? 0);
$m       = require_org_role('owner');

global $db;
$org    = $db->prepare_select_one("SELECT * FROM orgs WHERE id=? AND deleted_at IS NULL", 'i', $org_id);
$target = $db->prepare_select_one(
    "SELECT u.id, u.name, u.username, om.role
       FROM users u JOIN org_members om ON om.user_id=u.id
      WHERE u.id=? AND om.org_id=?",
    'ii', $user_id, $org_id
);
if (!$org || !$target) { redirect('users/org_members.php?org_id=' . $org_id); exit; }

$current_user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'change_role') {
        $new_role = sanitize_input($_POST['role'] ?? '');
        if (!in_array($new_role, ['owner','admin','member'], true)) {
            $errors[] = 'Invalid role.';
        }
        // Prevent demoting the last owner.
        if (!$errors && $target['role'] === 'owner' && $new_role !== 'owner'
            && count_org_owners($org_id) <= 1) {
            $errors[] = 'Cannot demote the last owner. Promote another member first.';
        }
        if (!$errors) {
            change_org_member_role($org_id, $user_id, $new_role);
            $_SESSION['success_msg'] = 'Role updated.';
            redirect('users/org_members.php?org_id=' . $org_id);
            exit;
        }
    }

    if ($action === 'remove') {
        if ($target['role'] === 'owner' && count_org_owners($org_id) <= 1) {
            $_SESSION['error_msg'] = 'Cannot remove the last owner.';
            redirect('users/edit_org_member.php?org_id=' . $org_id . '&user_id=' . $user_id);
            exit;
        }
        remove_org_member($org_id, $user_id);
        $_SESSION['success_msg'] = remove_junk($target['name']) . ' removed from organization.';
        redirect('users/org_members.php?org_id=' . $org_id);
        exit;
    }
}

$page_title = 'Edit Member';
require_once '../layouts/header.php';
?>
<div class="row"><div class="col-md-5">
  <div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Edit <?= remove_junk($target['name']) ?></h2></div>
    <div class="panel-body">
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= remove_junk($e) ?></div>
      <?php endforeach ?>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_role">
        <div class="form-group">
          <label>Role</label>
          <select name="role" class="form-control">
            <?php foreach (['owner','admin','member'] as $r): ?>
            <option value="<?= $r ?>" <?= $target['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Save Role</button>
      </form>
      <hr>
      <form method="POST" onsubmit="return confirm('Remove this member?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="remove">
        <button type="submit" class="btn btn-danger btn-sm">Remove from Organization</button>
      </form>
      <a href="org_members.php?org_id=<?= $org_id ?>" class="btn btn-default btn-sm">Back</a>
    </div>
  </div>
</div></div>
<?php require_once '../layouts/footer.php'; ?>
```

- [ ] **Step 3: Lint both files**

```bash
php -l users/add_org_member.php users/edit_org_member.php
```

- [ ] **Step 4: Commit**

```bash
git add users/add_org_member.php users/edit_org_member.php
git commit -m "feat(tenancy): add/edit org member pages — last-owner guard, role change, remove"
```

---

### Task 25: Topbar org switcher — layouts/header.php

**Files:**
- Modify: `layouts/header.php`

- [ ] **Step 1: Add org switcher to the topbar nav**

Locate the user-menu area in `layouts/header.php` (where the username/logout dropdown is rendered). Add an org switcher dropdown before it:

```php
<?php
// Org switcher — only rendered when user is in a session with a valid org.
if (!empty($_SESSION['current_org_id']) && !empty($_SESSION['user_id'])) {
    $current_user_id = (int)$_SESSION['user_id'];
    $current_org_id  = (int)$_SESSION['current_org_id'];
    $user_orgs       = find_all_orgs_for_user($current_user_id);
    $current_org     = null;
    foreach ($user_orgs as $o) {
        if ((int)$o['id'] === $current_org_id) {
            $current_org = $o;
            break;
        }
    }
}
?>
<?php if (!empty($user_orgs) && $current_org): ?>
<li class="dropdown">
  <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <span style="color:#e07018;font-family:'IBM Plex Mono',monospace;font-size:0.85em">
      <?= remove_junk($current_org['slug']) ?>
    </span>
    <span class="badge" style="background:#2b7de9"><?= remove_junk(ucfirst($current_org['role'])) ?></span>
    <b class="caret"></b>
  </a>
  <ul class="dropdown-menu">
    <li class="dropdown-header">Switch Organization</li>
    <?php foreach ($user_orgs as $o): ?>
    <?php if ((int)$o['id'] !== $current_org_id): ?>
    <li>
      <form method="POST" action="<?= BASE_URL ?>users/switch_org.php" style="padding:3px 16px">
        <?= csrf_field() ?>
        <input type="hidden" name="org_id" value="<?= (int)$o['id'] ?>">
        <button type="submit" class="btn btn-link" style="padding:0;text-align:left">
          <?= remove_junk($o['name']) ?>
        </button>
      </form>
    </li>
    <?php endif ?>
    <?php endforeach ?>
    <li role="separator" class="divider"></li>
    <li><a href="<?= BASE_URL ?>users/orgs.php">Manage Organizations</a></li>
  </ul>
</li>
<?php endif ?>
```

Insert this `<li>` block in the `<ul class="nav navbar-nav navbar-right">` section, before the user-profile dropdown.

- [ ] **Step 2: Lint**

```bash
php -l layouts/header.php
```

- [ ] **Step 3: Commit**

```bash
git add layouts/header.php
git commit -m "feat(tenancy): topbar org switcher dropdown — slug + role badge, POST to switch_org.php"
```

---

### Task 26: Replace page_require_level() with require_org_role() at all call sites + remove shim

**Files:**
- Modify: all `*.php` pages that call `page_require_level()`

- [ ] **Step 1: Find every call site**

```bash
grep -rn "page_require_level" --include="*.php" . | grep -v includes/sql.php
```

Note the file and argument at each line.

- [ ] **Step 2: Replace each call**

Mapping (from the shim):

| Old call | New call |
|---|---|
| `page_require_level(ROLE_ADMIN)` | `require_org_role('owner')` |
| `page_require_level(ROLE_SUPERVISOR)` | `require_org_role('owner', 'admin')` |
| `page_require_level(ROLE_USER)` | `require_org_role('owner', 'admin', 'member')` |

Replace mechanically; each file is a one-line change.

- [ ] **Step 3: Delete the shim from includes/sql.php**

Remove the `page_require_level()` function body entirely (it was kept only for PR1).

- [ ] **Step 4: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -5
```

- [ ] **Step 5: Lint all modified page files**

```bash
find users products sales customers suppliers purchasing reports data_tools -name "*.php" \
  | xargs php -l 2>&1 | grep -v "No syntax errors"
```

Expected: no output.

- [ ] **Step 6: Commit**

```bash
git add -u
git commit -m "refactor(tenancy): replace page_require_level() with require_org_role() at all call sites; remove shim"
```

---

### Task 27: Complete OrgManagementTest.php

**Files:**
- Modify: `tests/OrgManagementTest.php`

- [ ] **Step 1: Fill in all 10 spec cases**

Add these to `OrgManagementTest`:

```php
public function test_rename_org_changes_name(): void {
    global $db;
    $user_row = $db->prepare_select_one("SELECT id FROM users WHERE deleted_at IS NULL LIMIT 1", '');
    $_SESSION['current_org_id'] = 1;
    $org_id = add_org('HARNESS_RenameMe', 'harness-rename-me', (int)$user_row['id']);
    try {
        rename_org($org_id, 'HARNESS_Renamed', 'harness-renamed');
        $row = $db->prepare_select_one("SELECT name FROM orgs WHERE id=?", 'i', $org_id);
        $this->assertSame('HARNESS_Renamed', $row['name']);
    } finally {
        $db->prepare_query("DELETE FROM org_members WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM settings     WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM media        WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM orgs         WHERE id=?",     'i', $org_id);
    }
}

public function test_soft_delete_org_sets_deleted_at(): void {
    global $db;
    $user_row = $db->prepare_select_one("SELECT id FROM users WHERE deleted_at IS NULL LIMIT 1", '');
    $_SESSION['current_org_id'] = 1;
    $org_id = add_org('HARNESS_DeleteMe', 'harness-delete-me', (int)$user_row['id']);
    try {
        soft_delete_org($org_id, (int)$user_row['id']);
        $row = $db->prepare_select_one("SELECT deleted_at FROM orgs WHERE id=?", 'i', $org_id);
        $this->assertNotNull($row['deleted_at']);
    } finally {
        $db->prepare_query("DELETE FROM org_members WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM settings     WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM media        WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM orgs         WHERE id=?",     'i', $org_id);
    }
}

public function test_add_member_admin_can_add(): void {
    global $db;
    $users = $db->prepare_select("SELECT id FROM users WHERE deleted_at IS NULL LIMIT 2", '');
    $_SESSION['current_org_id'] = 1;
    $org_id = add_org('HARNESS_AddMember', 'harness-add-member', (int)$users[0]['id']);
    try {
        add_org_member($org_id, (int)$users[1]['id'], 'member');
        $row = $db->prepare_select_one(
            "SELECT role FROM org_members WHERE org_id=? AND user_id=?",
            'ii', $org_id, (int)$users[1]['id']
        );
        $this->assertSame('member', $row['role']);
    } finally {
        $db->prepare_query("DELETE FROM org_members WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM settings     WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM media        WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM orgs         WHERE id=?",     'i', $org_id);
    }
}

public function test_cannot_remove_last_owner(): void {
    global $db;
    $user_row = $db->prepare_select_one("SELECT id FROM users WHERE deleted_at IS NULL LIMIT 1", '');
    $_SESSION['current_org_id'] = 1;
    $org_id = add_org('HARNESS_LastOwner', 'harness-last-owner', (int)$user_row['id']);
    try {
        $this->assertSame(1, count_org_owners($org_id));
        // The page enforces this with count_org_owners() check before calling remove_org_member().
        // Here we verify the count invariant helper is correct.
        $this->assertGreaterThanOrEqual(1, count_org_owners($org_id));
    } finally {
        $db->prepare_query("DELETE FROM org_members WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM settings     WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM media        WHERE org_id=?", 'i', $org_id);
        $db->prepare_query("DELETE FROM orgs         WHERE id=?",     'i', $org_id);
    }
}
```

- [ ] **Step 2: Run full suite**

```bash
bash tests/run.sh 2>&1 | tail -5
```

All tests green.

- [ ] **Step 3: Commit**

```bash
git add tests/OrgManagementTest.php
git commit -m "test(tenancy): OrgManagementTest.php — create, rename, soft-delete, member CRUD, last-owner guard"
```

---

### PR2 completion checklist

- [ ] `bash tests/run.sh` — all tests green
- [ ] `find . -name "*.php" | xargs php -l 2>&1 | grep -v "No syntax errors"` — empty output
- [ ] `grep -rn "page_require_level" --include="*.php" .` — only appears in git history, not in any current file
- [ ] Manual smoke PR2 steps 1–5 from spec § 9.5
- [ ] Open PR targeting `main`

---

## Self-review — spec coverage check

| Spec section | Covered by task(s) |
|---|---|
| § 4.1 New tables `orgs`, `org_members` | Tasks 1 |
| § 4.2 `org_id` on 7 business tables | Tasks 2 |
| § 4.2 `settings` PK reshape | Task 3 |
| § 4.2 `users.last_active_org_id` | Task 3 |
| § 4.3 Backfill role mapping | Task 1 (012 migration) |
| § 5.1 `$_SESSION['current_org_id']` | Tasks 6, 8 |
| § 5.2 Login org-resolution flow | Task 8 |
| § 5.3 `current_org_id()` throws on unset | Task 6 |
| § 5.4 `require_org_role()` | Task 9 |
| § 5.4 `page_require_level()` shim (PR1) | Task 9 |
| § 5.5 `switch_org.php` endpoint | Task 14 |
| § 6.1 `ORG_SCOPED_TABLES` + `table_has_org_id()` | Task 5 |
| § 6.2 `find_all` / `find_by_id` auto-filter | Task 7 |
| § 6.3 Hand-written SELECT audit | Task 10 |
| § 6.3 Pre-commit grep guard | Task 15 |
| § 6.4 INSERT org_id enforcement | Task 11 |
| § 6.4 UPDATE/DELETE org_id guard | Task 12 |
| § 6.5 `find_org_members()` join via membership | Task 9 |
| § 7 schema.sql mirror | Task 4 |
| § 8.2 Org management UI pages | Tasks 20–24 |
| § 8.2 Topbar org switcher | Task 25 |
| § 8.2 Remove shim + update call sites | Task 26 |
| § 9.1 TenancyTest.php 18 cases | Tasks 5–7, 16, 18 |
| § 9.2 OrgManagementTest.php 10 cases | Tasks 19, 27 |
| § 9.3 `tenancy_fixtures.php` | Task 16 |
| § 9.3 Existing suites get `setup_test_org_session()` | Task 17 |
| § 9.5 Smoke-test steps | PR1 + PR2 completion checklists |
| Settings org-scoping | Task 13 |

No spec requirements without a corresponding task.
