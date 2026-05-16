# Gap Analysis

Snapshot of feature completeness, test coverage, and known issues for the Inventory Management System.

**Last regenerated**: 2026-05-16 (post-soft-delete merge, tenancy branch active)
**Codebase state**: `main` at `119f59b` (soft-delete merged); `feature/tenancy-schema` at `c288766` (migrations 010–021 staged)

---

## 1. What works (verified)

### Core workflows
- **Authentication**: bcrypt password hashing, SHA1 → bcrypt auto-upgrade on login, session-fixation prevention via `session_regenerate_id(true)`, login form CSRF, IP-based rate limiting (5 attempts / 15 min).
- **RBAC**: Three roles (Admin / Supervisor / User) enforced by `page_require_level()` on every protected page. Disabled users and disabled groups are both blocked (PHP 8.1+ int cast fix applied).
- **CRUD modules**: products, categories, customers, sales, orders, stock, media, users, user_groups, log.
- **Reports**: daily sales, monthly sales, sales-by-product, stock-low, with date-range filters.
- **Invoices & picklists**: PDF-style printable views from sales records.
- **Audit log**: every page request and state change recorded with user_id + IP.
- **CSRF**: POST forms use `verify_csrf()`; state-changing GET deletes use `verify_get_csrf()` + `csrf_url_param()`.
- **Output escaping**: `h()` helper used on all dynamic output in views.
- **CSP**: `script-src 'self'` and `style-src 'self'` emitted on every request from `load.php` via `header()`.
- **Security headers**: X-Frame-Options: DENY, X-Content-Type-Options: nosniff, Referrer-Policy, Permissions-Policy.
- **Password complexity**: `validate_password()` — min 8 chars, requires letter + digit, common-password denylist.
- **Soft-delete**: `users`, `customers`, `sales`, `orders`, `stock` all have `deleted_at TIMESTAMP`; `find_all()`/`find_by_id()` auto-filter; trash/restore/purge UI at `users/trash.php`, `users/restore.php`, `users/purge.php` (Admin-level-1 only).
- **Settings**: DB-backed `settings(setting_key, setting_value)` table; `Settings::get()` / `Settings::set()`; admin UI at `users/settings.php`; currency code configurable.
- **CI**: `.github/workflows/ci.yml` runs `php -l` lint + full test suite on push/PR.
- **Pre-commit hook**: `php -l` on staged PHP files via `.githooks/pre-commit` + `scripts/install-hooks.sh`.

### Verified by tests (62 total)

| Suite | Tests | Coverage |
|---|---|---|
| `AuthTest.php` | 9 / 9 pass | Login (all 3 roles), wrong password, non-existent user, SHA1→bcrypt migration, session fixation |
| `CSRFTest.php` | 16 / 16 pass | Token lifecycle, POST + GET verification, multibyte, stale-token rejection |
| `CRUDTest.php` | 11 / 11 pass | Product CRUD, quantity adjust, SQL-injection resistance |
| `SecurityHeadersTest.php` | 7 / 7 pass | CSP present, no unsafe-inline/eval, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy |
| `SettingsTest.php` | 6 / 6 pass | Settings::get() defaults, Settings::set() persistence, currency code round-trip, formatcurrency() fallback |
| `SoftDeleteTest.php` | 13 / 13 pass | table_has_soft_delete probe, soft-delete/restore/purge lifecycle, find_all/find_by_id filters, authenticate() rejects deleted user |

---

## 2. Recently fixed (2026-05-16 cycle)

| Fix | File | Severity |
|---|---|---|
| Soft-delete pattern: 5 migrations, sql.php helpers, trash/restore/purge UI, 13 tests | `migrations/005–009`, `includes/sql.php`, `users/trash.php` | HIGH |
| Settings table + currency admin UI + `Settings` class | `migrations/004`, `includes/settings.php`, `users/settings.php` | MEDIUM |
| CSP headers (`script-src 'self'`, `style-src 'self'`) — no inline styles or scripts | `includes/load.php`, `libs/css/main.css` | HIGH |
| Login CSRF — `auth.php` now verifies token at handler entry | `users/auth.php` | CRITICAL |
| Disabled-account bypass — `=== '0'` string compare on PHP 8.1+ int | `includes/sql.php:583,587` | CRITICAL |
| Stored XSS in log viewer, orders, stock, customer name column | `users/log.php`, `sales/sales_by_order.php`, `products/stock.php`, `customers/customers.php` | HIGH |
| Admin login redirect bug — role check used string compare on int from mysqli | `layouts/header.php:74–82` | CRITICAL |
| No login rate limiting — credential stuffing unmitigated | `users/auth.php`, `failed_logins` table | HIGH |
| No password complexity — single-char passwords accepted | `users/add_user.php`, `users/edit_user.php`, `users/change_password.php` | MEDIUM |
| `quantity` columns were VARCHAR(50) — arithmetic unsafe | `migrations/001_quantity_int` | HIGH |
| Upload.php `insert_media()` / `update_userImg()` used string interpolation | `includes/upload.php` | HIGH |
| CI pipeline missing | `.github/workflows/ci.yml` | MEDIUM |
| CRUDTest silent assert failures (PHP assert() is no-op by default) | `tests/CRUDTest.php` | MEDIUM |
| install.sh: `configtest` grep checked stdout, Apache writes to stderr | `install.sh` | MEDIUM |

---

## 3. Known issues (not yet fixed)

### Critical

**Products and categories have no soft-delete** — `delete_by_id()` on `products` or `categories` is a hard DELETE. This permanently removes records that are referenced in historical `sales` rows (via FK with CASCADE), breaking all historical reporting for deleted products.
- `SOFT_DELETE_TABLES` in `includes/sql.php:139` does not include `products` or `categories`.
- `schema.sql` has `products.deleted_at` as `datetime` (inconsistent with other tables' `timestamp`).
- Scope: add `deleted_at TIMESTAMP` migration, add both to `SOFT_DELETE_TABLES`, update product-specific SQL helpers.

**Hand-written SQL queries have no `org_id` filter (tenancy readiness)** — When tenancy lands (PR1 of `feature/tenancy-schema`), all queries that lack `WHERE org_id = ?` will return cross-tenant data.
- Affected: `join_product_table()` (sql.php:610), `find_all_product_info_by_title()` (sql.php:656), `find_all_sales()` (sql.php:923), `find_all_orders()` (sql.php:944), `find_all_product_info_by_sku()` (sql.php:697) and INSERT handlers in `sales/add_sale_by_sku.php`, `sales/add_sale_by_search.php`, `products/add_product.php`, `customers/add_customer.php`.
- These must be updated as part of tenancy PR1 before the feature ships.

**File upload MIME re-validation missing** — `includes/upload.php:67` calls `getimagesize()` but does not re-validate the MIME type against magic bytes after upload. Extension whitelist in `file_ext()` (upload.php:43) can be bypassed with a crafted file (e.g., shell.php.jpg with a prepended image header).
- Risk: stored PHP execution if Apache is configured to execute `.php` in `uploads/`.
- Fix: add `exif_imagetype()` or `finfo_file()` check after move_uploaded_file().

### Significant

**Zero tests for products, customers, sales, and reports modules** — `tests/CRUDTest.php` covers basic product insert/update/delete but there are no dedicated integration tests for:
- Customer CRUD workflows
- Sales/order creation and quantity decrease
- Report query functions (`find_sale_by_dates()`, `dailySales()`, `monthlySales()`)

**Login rate limiting is IP-only, not per-username** — `record_failed_login()` and `is_login_rate_limited()` in `sql.php` track by IP. An attacker rotating usernames across the same IP is not detected. Per-username lockout is standard practice.

**No numeric range validation on price/quantity fields** — `products/add_product.php:27–29` and `edit_product.php` call `remove_junk()` on `buy_price`, `sale_price`, and `quantity` but never validate that they are positive numbers or that `buy_price < sale_price`.

**`unlink()` failures are silent** — `includes/upload.php:223` and `271` call `unlink()` and return `true` unconditionally even if the file doesn't exist or permission is denied. Orphaned files accumulate with no log entry.

**No cleanup on failed `move_uploaded_file()`** — `includes/upload.php:127–137` checks the return value of `move_uploaded_file()` but does not roll back the `insert_media()` DB row if the file move fails. Leaves orphaned media rows in the database.

### Minor

**`find_by_name()` has no `deleted_at` filter** — `includes/sql.php:91–99` will return a soft-deleted record as "already exists", preventing re-creation of a previously deleted entity.

**`join_product_table()` uses legacy `find_by_sql()` and has no `deleted_at` filter** — `includes/sql.php:610–618`. Soft-deleted products appear in product listings until the query is updated.

**AJAX endpoints use `isUserLoggedIn()` instead of `page_require_level()`** — `sales/ajax_customer.php:10` and `ajax_sku.php` only verify login status, not user level. Inconsistent with the rest of the codebase.

**No audit logging for admin mutations** — `users/add_user.php`, `users/edit_user.php`, `users/delete_user.php` don't log to the `log` table. Failed logins are logged; successful admin actions (create/role-change/delete) are not.

**`products.deleted_at` column type is `datetime` not `timestamp`** — `schema.sql:197`. Inconsistency with the other 4 soft-delete tables which use `TIMESTAMP`. `soft_delete_by_id()` calls `NOW()` which works with both, but the inconsistency signals an incomplete migration.

---

## 4. Documented but missing

| Claim | Source doc | Status |
|---|---|---|
| "Soft-delete with restore" | gap-analysis.md (prior pass) | ✅ IMPLEMENTED 2026-05-16 — migrations 005–009, sql.php helpers, trash/restore/purge UI |
| "Per-tenant currency" | gap-analysis.md | ✅ IMPLEMENTED 2026-05-15 — single-install `settings` table; per-org pending tenancy PR2 |
| "Playwright UI tests" | gap-analysis.md | ❌ NOT IMPLEMENTED — scoped to future work |
| "CSP headers on all responses" | tech-stack.md | ✅ IMPLEMENTED 2026-05-15 — load.php emits CSP + security headers |
| "Rate limiting on login" | implied by security baseline | ✅ IMPLEMENTED 2026-05-15 — 5 attempts / 15 min per IP |
| "Migration 001 applied" | gap-analysis.md (prior pass) | ✅ APPLIED 2026-05-15 on live DB |
| "Upload.php prepared statements" | gap-analysis.md (prior pass) | ✅ IMPLEMENTED 2026-05-15 |
| "CI pipeline" | gap-analysis.md (prior pass) | ✅ ADDED 2026-05-15 — .github/workflows/ci.yml |

---

## 5. Tenancy status (`feature/tenancy-schema` branch)

Migrations 010–021 are complete. Spec and implementation plan reviewed.

**PR1 scope (schema + guards)**:
- `orgs` table, `org_members` table, default org seed
- `org_id` FK added to: customers, products, categories, sales, orders, stock, media
- `settings` PK reshaped to `(org_id, setting_key)`
- `users.last_active_org_id` added
- Auto-filter in `find_all()` / `find_by_id()` — requires org context in session
- `switch_org` endpoint — no UI yet
- Shim preserving `page_require_level()` call sites

**PR2 scope (org UI — future branch from main)**:
- Org create/rename/soft-delete
- Member add/role-change/remove
- Topbar org switcher
- Remove shim

**Outstanding before PR1 can ship**:
1. Wire `org_id` into session on login (`users/auth.php`)
2. Update all hand-written SQL queries (see Critical section above)
3. Update INSERTs to include `org_id` from session
4. Integration tests for `switch_org` endpoint
5. Audit existing test suite — HARNESS_ data must include `org_id`
