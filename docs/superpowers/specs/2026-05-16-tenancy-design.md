# Tenancy + Per-Org Currency — Design

- **Date:** 2026-05-16
- **Status:** Draft, awaiting user review
- **Scope:** Sub-project 2 of 3 (soft-delete → **tenancy / per-org currency** → Playwright UI tests)
- **Source memo:** `next_steps_inventory.md` § "Deferred work" item 2
- **Predecessor:** PR #30 shipped a single-tenant `settings(setting_key)` table with `currency_code=USD`. This spec moves the currency (and the table) under an org.

## 1. Goals

Make the inventory app multi-org-aware on a single deployment, so one CoreConduit-hosted Pi can serve a consultant or volunteer who administers multiple unrelated organizations. Each org's customers, products, sales, orders, and stock are isolated from every other org. Users are global identities; org membership is an explicit join with a per-org role (owner / admin / member). The per-org currency setting from PR #30 moves under this model as the first concrete tenancy use case.

## 2. Scope

### In scope

- New `orgs` and `org_members` tables.
- `org_id` column on seven business tables: `customers`, `products`, `categories`, `sales`, `orders`, `stock`, `media`.
- Reshape of `settings` PK to `(org_id, setting_key)`.
- `users.last_active_org_id` nullable pointer for default-org-on-login.
- Auto-backfill migration: creates one "Default Organization" row, assigns every existing business row to it, enrolls every existing user as a member with a role mapped from `users.user_level`.
- Read/write/update/delete path filters every org-scoped table on `current_org_id()`; missing the filter is caught by a `find_all` / `find_by_id` auto-filter plus an audit pass on every hand-written query.
- New `require_org_role(...$allowed)` role gate; `page_require_level()` retained as a shim during PR1.
- POST endpoint `users/switch_org.php` (CSRF-protected) lands in PR1; UI for it lands in PR2.
- UI for org create, rename, soft-delete, member add / role change / remove lands in PR2.

### Out of scope (deliberate)

1. Email-based invitations. Members are added by username dropdown only.
2. Self-serve org signup / anonymous registration.
3. Org-scoped audit log. `log` stays global.
4. Org-scoped `user_groups`. The three-tier `owner/admin/member` enum is fixed for now.
5. Cross-org product/customer transfers.
6. Composite FK enforcement (e.g., "a sale's product must be in the same org as the sale"). Enforced in application code on insert; not in the DB.
7. Dropping `users.user_level`. The column stays through PR1 and PR2 to avoid breaking the existing `user_groups` FK.
8. Playwright UI tests — sub-project 3, separate from this work.
9. Per-org branding (logos, theme colors).
10. Soft-delete cascade across orgs. When an org is soft-deleted, its rows are not soft-deleted en masse; members lose access via the membership check.

## 3. Tenancy model (the architectural answers)

These are pinned by the brainstorming Q&A; the design below depends on each:

- **Use case:** Single-org-always-but-users-belong-to-many. A user logs in once and can switch between orgs they're a member of.
- **Org scope:** Business data only — `customers`, `products`, `categories`, `sales`, `orders`, `stock`, `media`. Identity tables (`users`, `user_groups`, `log`, `failed_logins`) stay global.
- **Role model:** Per-membership. `org_members(user_id, org_id, role)` where `role IN ('owner','admin','member')`. The legacy `users.user_level` is not used for tenancy authorization.
- **Backfill:** Auto-create `orgs(id=1, slug='default', name='Default Organization')`, backfill every business row to `org_id=1`, enroll every existing user (mapping `user_level=1 → owner`, `2 → admin`, `3 → member`).
- **New-org creation:** Any user creates orgs from the UI (PR2); creator gets `role='owner'`. Owners and admins of an org can add existing users as members.
- **Default-org-on-login:** `users.last_active_org_id` if still a member there, else oldest membership by `joined_at`, else login is rejected with a clear "no organization access" message.
- **Settings shape:** `settings` gets `org_id` added, PK reshaped to `(org_id, setting_key)`. Currency moves to per-org with no code change at call sites beyond `Settings::get/set` becoming org-scoped.

## 4. Data model

### 4.1 New tables

```sql
CREATE TABLE `orgs` (
    `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `slug`       VARCHAR(60)  NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP    NULL DEFAULT NULL,
    `deleted_by` INT(11) UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orgs_slug` (`slug`),
    KEY `idx_orgs_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_orgs_deleted_by`
      FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

`orgs` reuses the soft-delete shape from PR #34 so an org can be archived without nuking data.

### 4.2 Existing-table changes

| Table       | Change |
|-------------|--------|
| `customers`  | `+ org_id INT(11) UNSIGNED NOT NULL DEFAULT 1`, FK to `orgs(id)`, composite index `(org_id, deleted_at)`, `UNIQUE(name)` → `UNIQUE(org_id, name)` |
| `products`   | `+ org_id …`, composite index `(org_id)`, `UNIQUE(name)` → `UNIQUE(org_id, name)` |
| `categories` | `+ org_id …`, composite index `(org_id)`, `UNIQUE(name)` → `UNIQUE(org_id, name)` |
| `sales`      | `+ org_id …`, composite index `(org_id, deleted_at)` |
| `orders`     | `+ org_id …`, composite index `(org_id, deleted_at)` |
| `stock`      | `+ org_id …`, composite index `(org_id, deleted_at)` |
| `media`      | `+ org_id …`, composite index `(org_id)`. The seed `(1,'no-image.png','image/png')` row stays on `org_id=1`. Future org creation re-seeds an identical row for the new org. |
| `users`      | `+ last_active_org_id INT(11) UNSIGNED NULL`, FK to `orgs(id) ON DELETE SET NULL` |
| `settings`   | drop `PRIMARY KEY (setting_key)`, `+ org_id INT(11) UNSIGNED NOT NULL DEFAULT 1`, recreate `PRIMARY KEY (org_id, setting_key)`, FK to `orgs(id) ON DELETE CASCADE` |

`users.user_level` is **kept** in PR1 and PR2 to avoid breaking the existing `user_groups` FK; a later cleanup PR removes it once nothing reads it.

### 4.3 Role mapping for backfill

```sql
INSERT INTO org_members (org_id, user_id, role)
SELECT 1, u.id,
       CASE u.user_level
         WHEN 1 THEN 'owner'
         WHEN 2 THEN 'admin'
         ELSE        'member'
       END
FROM users u
WHERE u.deleted_at IS NULL;
```

Soft-deleted users are not enrolled. If a soft-deleted user is later restored, they get re-enrolled at restore time (PR2 adds the enrollment to the restore flow; PR1 documents the gap).

## 5. Auth + session flow

### 5.1 Session shape

```php
$_SESSION['user_id']        = 42;       // unchanged
$_SESSION['current_org_id'] = 1;        // new — drives every read/write
```

The `current_org_id` is server-side only. The switcher endpoint accepts an `org_id` from the client but validates membership before writing it to the session.

### 5.2 Login flow

`index.php` calls `authenticate($username, $password)`. On success:

1. Look up `users.last_active_org_id` for that user.
2. If set and the user still has a row in `org_members(org_id=last_active_org_id, user_id=...)` for an org that is not soft-deleted → use it.
3. Else → pick the oldest membership by `joined_at` for a non-soft-deleted org.
4. Else → reject login with the message `"You have no organization access. Contact your administrator."`

`Session::login($user_id, $org_id)` stores both values and calls `session_regenerate_id(true)`.

### 5.3 `current_org_id()` helper

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

Throws loudly when called outside a session. This catches "forgot to start a session" bugs at the read site, instead of silently leaking org 1's data.

### 5.4 Role gate

```php
function require_org_role(string ...$allowed): array {
    $user = current_user();
    $org_id = current_org_id();
    $m = find_membership($user['id'], $org_id);
    if (!$m) { /* redirect with 'No access' */ }
    if (!in_array($m['role'], $allowed, true)) { /* redirect with 'No permission' */ }
    return $m;
}
```

Old `page_require_level()` is kept in PR1 as a shim:

```php
function page_require_level($require_level) {
    // Shim during PR1: maps the old numeric tier to role names.
    $map = [1 => ['owner'], 2 => ['owner','admin'], 3 => ['owner','admin','member']];
    require_org_role(...$map[$require_level]);
}
```

PR2 removes the shim and updates the ~30 call sites.

### 5.5 Org switcher endpoint

`users/switch_org.php` — POST only, `verify_csrf()` first, accepts a single `org_id` form field:

1. Verify `org_members(user_id=current_user, org_id=posted)` exists for a non-soft-deleted org.
2. On hit: `$_SESSION['current_org_id'] = $posted; UPDATE users SET last_active_org_id = ? WHERE id = ?;` then redirect to `home.php`.
3. On miss: redirect with an error flash. Session unchanged.

PR1 ships this endpoint with no UI; PR2 wires the topbar dropdown to it.

## 6. Read / write / update / delete path

### 6.1 Defense-in-depth constants

```php
// includes/sql.php
const ORG_SCOPED_TABLES = [
    'customers', 'products', 'categories',
    'sales', 'orders', 'stock', 'media',
];

function table_has_org_id(string $table): bool {
    // Cached column-exists probe, identical pattern to table_has_soft_delete().
    // Returns false when org_id is absent (the deploy → migrate window).
}
```

Three layers, all must agree:

1. The migrations themselves (DB has the column).
2. The `ORG_SCOPED_TABLES` allowlist in PHP.
3. The runtime probe `table_has_org_id()` (caches a column-exists check per request).

If any one is missing, reads run un-filtered, but since the only org that exists in a half-migrated DB is org 1, no data is leaked.

### 6.2 `find_all` / `find_by_id` auto-filter

```php
function find_all(string $table): array {
    $where = [];
    $params = []; $types = '';
    if (table_has_org_id($table) && in_array($table, ORG_SCOPED_TABLES, true)) {
        $where[]  = 'org_id = ?';
        $params[] = current_org_id();
        $types   .= 'i';
    }
    if (table_has_soft_delete($table) && in_array($table, SOFT_DELETE_TABLES, true)) {
        $where[] = 'deleted_at IS NULL';
    }
    // ... existing query assembly with prepared statement
}
```

`find_by_id`, `find_by_id_with_deleted` get the same `org_id = ?` predicate.

### 6.3 Hand-written-query audit pass

Roughly 25 hand-written `SELECT`s in `includes/sql.php` reference org-scoped tables. Each gets an `AND <alias>.org_id = ?` clause and a `current_org_id()` bind. The audit is mechanical; the spec's implementation plan will list every line number.

A pre-commit grep guard (extending PR #33) catches regressions:

```bash
git diff --cached -- '*.php' \
  | grep -E 'FROM\s+(customers|products|categories|sales|orders|stock|media)\b' \
  | grep -v -E 'org_id\s*=' \
  && echo "✗ org-scoped table referenced without org_id filter" && exit 1
```

### 6.4 Insert / update / delete

- **INSERT** into any `ORG_SCOPED_TABLES` table must include `org_id = current_org_id()`. There are ~12 insert sites (one per `add_*.php` plus a few in `sql.php` helpers).
- **UPDATE / DELETE** on these tables must include `AND org_id = current_org_id()` in the `WHERE` clause. This means an attacker who guesses an id from another org can't modify it.
- **Soft-delete** (`delete_resource()` from PR #34) gets the same predicate.

### 6.5 Users list — JOIN-via-membership

Users stays global, but the user-management pages show only members of the current org:

```php
function find_org_members(int $org_id): array {
    // SELECT u.id, u.name, u.username, m.role
    //   FROM users u
    //   JOIN org_members m ON m.user_id = u.id
    //  WHERE m.org_id = ? AND u.deleted_at IS NULL
    //  ORDER BY u.name
}
```

## 7. Migrations + backfill

Twelve new migration pairs, numbered `010-021`, following the existing `NNN_name.{up,down}.sql` convention. Ordering is chosen so each step is independently reversible and the schema is never in a state where a read could return wrong data.

```
010_orgs_table              CREATE TABLE orgs
011_org_members_table       CREATE TABLE org_members
012_default_org_seed        INSERT (1,'Default Organization','default') + org_members backfill

013_customers_org_id        ADD org_id NOT NULL DEFAULT 1; reshape UNIQUE; add composite index
014_products_org_id         same pattern
015_categories_org_id       same pattern
016_sales_org_id            same pattern
017_orders_org_id           same pattern
018_stock_org_id            same pattern
019_media_org_id            same pattern

020_settings_org_id         drop PK(setting_key); add org_id; PK(org_id, setting_key); FK to orgs
021_users_last_active_org   ADD last_active_org_id NULL with FK to orgs(id) ON DELETE SET NULL
```

### 7.1 Why this exact order

- `010`-`011` create the new tables; they touch nothing existing.
- `012` is the identity-seed step. It runs while business tables still have no `org_id` — if this fails, no business data is altered.
- `013`-`019` each add `org_id` to one business table at a time. The `DEFAULT 1 NOT NULL` shape means each `ALTER` is atomic — no two-step "add nullable, backfill, alter NOT NULL" dance is required because every existing row legitimately belongs to org 1.
- `020` reshapes `settings` after org 1 already exists (required by the FK).
- `021` adds the optional `users.last_active_org_id` last; it's nullable so no backfill is needed.

### 7.2 Down migrations

Each `.down.sql` reverses its `.up.sql`: drop column, drop table, restore old PK. `012_default_org_seed.down.sql` does **not** delete the seed rows — down migrations roll back schema, not data. If a real data rollback is ever needed, an explicit script in `scripts/` will do it (out of scope unless the scenario hits).

### 7.3 Live-DB application

Same flow as migrations 005-009: sudo-gated, harness auto-denies, user runs it manually between PR merge and smoke. The PR description will spell out:

```bash
mysqldump -u root inventory > inventory-pre-010-021.sql
cat migrations/010_*.up.sql migrations/011_*.up.sql migrations/012_*.up.sql \
    migrations/013_*.up.sql migrations/014_*.up.sql migrations/015_*.up.sql \
    migrations/016_*.up.sql migrations/017_*.up.sql migrations/018_*.up.sql \
    migrations/019_*.up.sql migrations/020_*.up.sql migrations/021_*.up.sql \
  | sudo mysql -u root inventory
```

### 7.4 `schema.sql` mirror

Same pattern as PR #34. Every migration's effect mirrored into `schema.sql` so fresh installs via `install.sh` get the post-migration state without replaying migrations.

## 8. PR1 / PR2 split

### 8.1 PR1 — `feature/tenancy-schema`: schema + invisible plumbing

For a deployment with exactly one org (e.g., blueberry), the app looks identical to today. The cross-org leak prevention is real (filters are live), but there's only one org for filters to discriminate between.

**Ships:**

- Migrations `010-021`
- `schema.sql` mirror updated
- `includes/sql.php` changes:
  - `ORG_SCOPED_TABLES` const + `table_has_org_id()` probe
  - `current_org_id()` helper (throws on unset)
  - `find_all` / `find_by_id` / `find_by_id_with_deleted` auto-filter for org-scoped tables
  - Audit pass: every hand-written `SELECT` from org-scoped tables gets `org_id = ?`
  - Every INSERT into org-scoped tables sets `org_id = current_org_id()`
  - Every UPDATE / DELETE adds `AND org_id = current_org_id()`
  - `find_org_members($org_id)` helper
  - `require_org_role(...$allowed)` new function; `page_require_level()` kept as a shim
- `includes/session.php`: `Session::login($user_id, $org_id)` signature; session writes `current_org_id`
- `includes/sql.php::authenticate()` extended to return `(user_id, org_id)` per § 5.2; `index.php` updated
- `users/switch_org.php` endpoint (POST, CSRF, no UI yet)
- `Settings::get/set` scoped by `current_org_id()`
- `.githooks/pre-commit` extended with the org-scoping grep guard
- `tests/TenancyTest.php` — see § 9.1
- Existing test suites updated to set up a current_org_id in fixtures (§ 9.3)

**Does NOT ship:**

- Org-management UI (orgs list, new org, edit org, members list, add/remove member)
- Topbar org switcher dropdown
- Updating the ~30 page-level `page_require_level()` call sites (shim absorbs them)

### 8.2 PR2 — `feature/tenancy-ux`: UX surface

Branches from `main` after PR1 lands and is smoked.

**Ships:**

- New pages under `users/`:
  - `users/orgs.php` — list orgs the current user is a member of
  - `users/add_org.php` — create new org (any logged-in user; creator gets `role='owner'`)
  - `users/edit_org.php` — rename / soft-delete (owner only)
  - `users/org_members.php` — list members with role chips
  - `users/add_org_member.php` — username dropdown + role select (owner / admin only)
  - `users/edit_org_member.php` — change role / remove from org (owner only; can't remove the last owner)
- Topbar org switcher dropdown in `layouts/header.php` (POSTs to `switch_org.php`)
- Replace `page_require_level()` calls site-by-site with `require_org_role()`; delete the shim
- Restore-soft-deleted-user flow (PR #34) extended to re-enroll the user in the relevant org(s) — closes the gap noted in § 4.3
- `tests/OrgManagementTest.php` — see § 9.2
- Brand: org switcher uses brand-blue chip + IBM Plex Mono for org slug; role badges (Owner / Admin / Member) follow CoreConduit silver theme

## 9. Testing strategy

The bar is set by `SoftDeleteTest` (13 cases, skips cleanly when migrations 005-009 absent). Tenancy tests follow the same pattern: a single test file per PR that runs on a migrated DB and skips gracefully on an unmigrated one.

### 9.1 PR1 — `tests/TenancyTest.php` (18 cases)

Backfill correctness (5):

1. `orgs` has exactly one row, `(id=1, slug='default')`.
2. Every non-deleted user has exactly one `org_members` row in org 1.
3. Role mapping is correct: at least one Admin-tier user got `role='owner'`, Supervisor got `admin`, User got `member`.
4. Every existing customer / product / category / sale / order / stock / media row has `org_id = 1`.
5. The `settings.currency_code` row is on `org_id = 1`.

Read-path filter (4):

6. `find_all('customers')` with `current_org_id=1` returns the seeded rows.
7. `find_all('customers')` with `current_org_id=2` after `seed_multi_org_fixture()` returns only org 2's row — the leak-prevention test.
8. `find_by_id('products', $id_from_org_2)` while `current_org_id=1` returns null.
9. `current_org_id()` throws when `$_SESSION['current_org_id']` is unset.

Write / update / delete-path (3):

10. INSERT into `customers` without explicit `org_id` is rejected by the NOT NULL constraint.
11. UPDATE on a row belonging to another org affects zero rows.
12. DELETE (soft) on a row belonging to another org affects zero rows.

Auth flow (4):

13. Login with valid creds + valid `last_active_org_id` lands the user in that org.
14. Login with `last_active_org_id` pointing to an org the user is no longer in → falls back to oldest membership.
15. Login with `last_active_org_id` pointing to a soft-deleted org → falls back to oldest membership.
16. Login for a user with zero memberships → authentication rejected.

Switch endpoint (2):

17. POST `/users/switch_org.php` with an `org_id` the user is a member of → succeeds, updates session + `last_active_org_id`.
18. POST with an `org_id` the user is NOT a member of → rejected, session unchanged.

### 9.2 PR2 — `tests/OrgManagementTest.php` (10 cases)

1. Create org → creator gets `role='owner'`, `last_active_org_id` updated to new org.
2. Rename org succeeds when role is owner.
3. Rename org rejected when role is admin or member.
4. Soft-delete org succeeds when role is owner; membership rows preserved; `users.last_active_org_id` cleared by FK `ON DELETE SET NULL` for affected users.
5. Add member by username — owner can, admin can, member can't.
6. Add member fails when target user doesn't exist.
7. Add member fails when target user is already a member.
8. Remove member — owner can remove anyone except themselves if they're the last owner.
9. "Can't remove last owner" — the invariant test.
10. Change role — owner can promote/demote; can't demote themselves if they're the last owner.

### 9.3 Test fixtures — `tests/lib/tenancy_fixtures.php`

```php
function seed_test_org(string $slug = 'default', string $name = 'Default Organization'): int;
function seed_multi_org_fixture(): array;     // returns ['org_id', 'customer_id', 'product_id', 'sale_id']
function cleanup_multi_org_fixture(): void;   // DELETE WHERE org_id = 2 AND slug = 'other'
```

`seed_test_org()` is idempotent and called by every test file's setup that needs `current_org_id` in session. `seed_multi_org_fixture()` is only called by cross-org leak tests (#7, #8, #11, #12). Single-org tests don't pay the setup cost. App code paths for org creation are tested via the UI flow in PR2 — schema-side tests do not exercise `add_org.php`.

`CRUDTest`, `AuthTest`, `SoftDeleteTest`, `SettingsTest` get a shared `setup_test_org_session()` helper added at the top: sets `$_SESSION['current_org_id'] = 1` and ensures `orgs(id=1)` exists. The CRUD assertions themselves don't change — they're already implicitly testing the default org.

### 9.4 Test commands (unchanged)

```bash
bash tests/run.sh
php tests/TenancyTest.php
php tests/OrgManagementTest.php
```

### 9.5 Smoke-test plan (manual, post-merge, on blueberry)

**PR1:**

1. Log in as admin → land in Default Organization → every existing page renders unchanged.
2. SQL-insert a second org + a second user + a membership for admin in both orgs.
3. Switch orgs via the endpoint with `curl` + session cookie; confirm `/customers` returns different data.
4. Confirm customers / products created in org 2 don't appear in org 1's lists.
5. Confirm CSP headers still clean (no inline-style regressions).

**PR2:**

1. Topbar shows org switcher; switching reloads scoped data, URL unchanged.
2. Create a new org from the UI; land in it; create a customer; switch away; switch back; customer still there.
3. Add a second user as member; log out; log in as them; confirm they only see member-allowed pages.
4. Try to demote the last owner → blocked with a clear error.
5. Soft-delete an org; confirm members lose access on next request.

## 10. Known limitations

1. **Composite FKs not enforced** — a sale's `product_id` could theoretically reference a product from another org. Prevented by app-level checks on insert; not by the DB. Documented here so future readers know it's deliberate.
2. **`media_id=0` sentinel** — the existing seed row `(1,'no-image.png')` remains on `org_id=1`. New orgs get their own seed row. Code referencing `media_id=0` as a global "no image" sentinel needs an audit during implementation; the spec assumes the audit finds <5 sites and they can be normalized to per-org `no-image` rows.
3. **`user_groups` still global** — every org sees the same role names. Per-org custom role names are explicitly out of scope.
4. **No platform super-admin** — any org owner has equal weight. There is no deployment-tier admin that can see every org's data. If one is needed later, a `users.is_platform_admin BOOLEAN` column would be additive.
5. **Soft-deleted users not re-enrolled on restore in PR1** — PR2 closes the gap. PR1 documents it.

## 11. References

- Predecessor spec: `docs/superpowers/specs/2026-05-16-soft-delete-design.md`
- Next-steps memo: `~/.claude/projects/-home-coreconduit-inventory/memory/next_steps_inventory.md`
- PR #30 (single-tenant settings): merge commit `a1d6b2a`
- PR #34 (soft-delete pattern): merge commit `16ea448`
