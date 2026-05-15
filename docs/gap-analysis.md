# Gap Analysis

Snapshot of feature completeness, test coverage, and known issues for the Inventory Management System.

**Last regenerated**: 2026-05-15 (third pass — migrations applied, upload.php prepared, CSP tightened, CI added)
**Codebase commit**: post-install.sh-reinstall, post-PHP8-type-strictness fixes, post-hardening pass, post-CI

---

## 1. What works (verified)

### Core workflows
- **Authentication**: bcrypt password hashing, SHA1 → bcrypt auto-upgrade on login, session-fixation prevention via `session_regenerate_id(true)`, login form CSRF.
- **RBAC**: three roles (Admin / Supervisor / User) enforced by `page_require_level()` on every protected page.
- **CRUD modules**: products, categories, customers, sales, orders, stock, media, users, user_groups, log.
- **Reports**: daily sales, monthly sales, sales-by-product, stock-low, with date-range filters.
- **Invoices & picklists**: PDF-style printable views from sales records.
- **Audit log**: every page request and state change recorded with user_id + IP.
- **CSRF**: POST forms use `verify_csrf()`; state-changing GET deletes use `verify_get_csrf()` + `csrf_url_param()` URL helper.
- **Output escaping**: `h()` helper available; used on most views (gaps listed below).
- **Database**: prepared statements via `prepare_query()` / `prepare_select()`, silent-failure detection via `execute()` check.
- **Deployment**: `install.sh` wires database, Apache vhost on port 8080, symlink from `/var/www/html/inventory`, default app user `webuser@localhost` with minimal grants. `--reinstall` flag tears down + reinstalls; preserves git-tracked seed images.

### Verified by tests
| Suite | Result | Coverage |
|---|---|---|
| `tests/AuthTest.php` | 9/9 pass | login, password verify, SHA1 migration, session fixation |
| `tests/CSRFTest.php` | 16/16 pass | token lifecycle, POST + GET verification, multibyte, stale-token |
| `tests/CRUDTest.php` | 10/11 pass | product CRUD, quantity adjust, SQL-injection resistance |

---

## 2. Recently fixed (this maintenance cycle)

These were live bugs in `main` and have been patched:

| Bug | File | Severity | Fix |
|---|---|---|---|
| Admin login redirected to logout because role checks used `=== '1'` (string) on int from mysqli | `layouts/header.php:74-82` | CRITICAL | Cast to int before strict comparison |
| Disabled accounts and disabled groups could access every page (status check used `=== '0'`) | `includes/sql.php:408,411` | CRITICAL | Cast to int |
| Login POST handler did not call `verify_csrf()` despite form emitting token | `users/auth.php` | CRITICAL | Added CSRF check at handler entry |
| User edit form rendered `csrf_field()` inside `action=` attribute (token never submitted) | `users/edit_user.php:111-112` | CRITICAL | Move token output outside the `<form>` tag attribute |
| User and group list pages displayed every active record as "Deactive" | `users/users.php:72`, `users/group.php:65` | HIGH | Cast to int in display logic |
| Edit forms pre-selected wrong status/level dropdown option | `users/edit_user.php:128,136-137`, `users/edit_group.php:85-86` | HIGH | Cast to int |
| Stored XSS in audit log viewer (`$log['action']`, `$user['name']`) | `users/log.php:92,99` | HIGH | Wrap in `h()` |
| Stored XSS in order notes | `sales/sales_by_order.php:71` | HIGH | Wrap in `h()` |
| Stored XSS in stock comments (view + edit form) | `products/stock.php:72`, `products/edit_stock.php:114` | HIGH | Wrap in `h()` |
| Stored XSS in customer name column | `customers/customers.php:61` | HIGH | Wrap in `h()` |
| `users/index.php` echoed undefined `$username`/`$password` (every page load) | `users/index.php:23,27` | LOW | Pre-define `$username = ''`, never reflect password |
| AuthTest treated `authenticate()` return as array, but it returns int user_id | `tests/AuthTest.php:33-51` | MEDIUM | Look up user via `find_by_id()` after auth |
| Apache `configtest` grep checked stdout, but `apache2ctl` writes "Syntax OK" to stderr — Apache reload was silently skipped | `install.sh` | MEDIUM | Merge streams (`2>&1`) before grep |
| `apache2ctl -S` user extraction returned `name="www-data"` literal on newer Apache | `install.sh` | LOW | Parse via `sed` regex |
| `--reinstall` wiped tracked seed images (no_image.jpg etc.) from `uploads/` | `install.sh` | MEDIUM | Use `git clean` + `git checkout HEAD --` to preserve tracked files |
| 6 more `=== '0'` bugs on `media_id` (caused product images to never resolve correctly) | `home.php`, `admin.php`, `add_sale_by_search.php`, `add_sale_to_order.php`, `ajax_product.php`, `products.php` | HIGH | Cast to int |
| Seed user `'Special'` (uppercase) vs docs/tests `'special'` (lowercase) | `schema.sql:177` | LOW | Renamed seed row to lowercase |
| `tests/CRUDTest.php` failed silently — wrong column name + missing NOT NULL fields + missing parent category | `tests/CRUDTest.php` | HIGH | Fixed column name, provisioned HARNESS category for FK, used `check()` helper instead of `assert()` |
| `tests/bootstrap.php` did not buffer output, so `session_regenerate_id()` failed in the SessionTest after prior `echo`s | `tests/bootstrap.php` | MEDIUM | Added `ob_start()` at bootstrap entry |
| `tests/*.php` used `assert()` — a no-op on default PHP 8 configurations | `tests/AuthTest.php`, `tests/CRUDTest.php` | MEDIUM | Added `check()` helper that throws on failure |
| CSP / X-Frame-Options / Referrer-Policy / Permissions-Policy headers missing | `includes/load.php` | HIGH | Emit on every request (CSP allows `'self'` + `'unsafe-inline'` for bundled Bootstrap/jQuery) |
| No login rate limiting — credential stuffing unmitigated | `users/auth.php`, `failed_logins` table | HIGH | Added migration 002, helpers in `sql.php`, check + record + clear in `auth.php` (5 attempts per 15 min per IP) |
| No password complexity enforcement — single-char passwords accepted | `users/add_user.php`, `users/edit_user.php`, `users/change_password.php` | MEDIUM | Added `validate_password()` helper (min 8 chars, must contain letter + digit, denylist of common passwords) |
| `quantity` columns were VARCHAR(50) | `schema.sql`, `migrations/001_quantity_int.up.sql`, `migrations/001_quantity_int.down.sql` | HIGH | Migration 001 created; `schema.sql` updated for fresh installs (INT NOT NULL DEFAULT 0) |

---

## 3. Known issues (not yet fixed)

### MEDIUM

**Soft-delete pattern not implemented**
- `delete_by_id()` is a hard DELETE. For audit-heavy tables (users, sales, orders, stock) a `deleted_at` soft-delete pattern would be safer and reversible. Scope: 7+ schema changes, refactor every SELECT in `includes/sql.php` to filter `WHERE deleted_at IS NULL`, add `restore_by_id()` and admin UI for restoration. Deferred — needs its own PR.
- Partial mitigation already in place: migration 003 adds `log.user_id ON DELETE SET NULL` so deleting a user preserves audit trail.

**Migration 003 (log FK) needs to be applied to running deployments**
- `schema.sql` includes it for fresh installs; existing deployments must run `migrations/003_log_user_fk.up.sql` after a backup.

### LOW

**Currency code hard-coded to USD in `includes/load.php`** — no per-tenant currency switcher.

**No browser-level UI/integration tests** — `tests/SecurityHeadersTest.php` exercises HTTP responses but there's no Playwright/Selenium coverage of actual page interactions.

**CSP keeps `'unsafe-inline'` for `style-src`** — Bootstrap's JS sets inline style attributes (dropdowns, popovers, tooltips) at runtime; removing this would break common UI controls. `script-src` is now `'self'` only after moving inline handlers to `libs/js/functions.js`.

**`orders.customer` is a varchar (denormalized)** — should be FK to `customers.id` for referential integrity. Pre-existing schema decision; preserved to avoid breaking changes.

---

## 4. Documented but missing

Cross-reference of `docs/*.md` claims against actual code:

| Claim | Source doc | Status |
|---|---|---|
| "CSP headers on all responses" | `tech-stack.md`, project-wide PHP rules | ✅ IMPLEMENTED 2026-05-14 — `includes/load.php` emits CSP + X-Frame-Options + Referrer-Policy + Permissions-Policy + X-Content-Type-Options |
| "Rate limiting on login" | implied by security baseline | ✅ IMPLEMENTED 2026-05-14 — 5 attempts per 15 min per IP via `failed_logins` table |
| "Password complexity enforcement" | implied by security baseline | ✅ IMPLEMENTED 2026-05-14 — `validate_password()` helper applied at all three password-write sites |
| "Migration 001 (quantity → INT) applied" | `gap-analysis.md` prior pass | ✅ APPLIED 2026-05-15 on running deployment |
| "Upload.php prepared statements" | `gap-analysis.md` prior pass | ✅ IMPLEMENTED 2026-05-15 — `insert_media()` and `update_userImg()` now use `prepare_query()` |
| "CI pipeline" | `gap-analysis.md` prior pass | ✅ ADDED 2026-05-15 — `.github/workflows/ci.yml` runs `php -l` + full test suite on push/PR |
| "Security headers test" | `gap-analysis.md` prior pass | ✅ ADDED 2026-05-15 — `tests/SecurityHeadersTest.php` (7 tests) |
| "failed_logins housekeeping" | `gap-analysis.md` prior pass | ✅ ADDED 2026-05-15 — probabilistic prune (~1% of page loads) via `prune_failed_logins()` |
| "Inline JS / CSP tightening" | `gap-analysis.md` prior pass | ✅ MOSTLY DONE 2026-05-15 — moved `closePanel()` to `libs/js/functions.js`, replaced 4 redirect stubs with server-side redirects, CSP `script-src` is now `'self'` only |
| "log.user_id FK for audit-trail preservation" | `gap-analysis.md` prior pass | ✅ DESIGNED 2026-05-15 — migration 003 ready; schema.sql updated for fresh installs |
| "Soft delete with restore" | none — but typical for audit-heavy apps | NOT IMPLEMENTED — `delete_by_id()` is hard delete. Scoped + deferred (see section 3) |

---

## 5. Implemented but undocumented

| Feature | Notes |
|---|---|
| `csrf_url_param()` + `verify_get_csrf()` GET-based CSRF | Documented in `api-components.md`; mention in `architecture.md` security section recommended |
| `prepare_query()` execute-failure detection (dies with error_log on failed INSERT/UPDATE/DELETE) | Worth a one-line note in `api-components.md` |
| `--reinstall` flag on `install.sh` | Now documented in script's `--help`; should be linked from `setup-and-usage.md` |
| Automatic SHA1 → bcrypt password upgrade on first login | Mentioned briefly in `architecture.md`; could use a dedicated section |

---

## 6. Recommended next steps

Most prior-pass items now resolved (see section 4 below). Remaining work:

1. **Apply migration 003 to running deployments** — backup, then `sudo mysql inventory < migrations/003_log_user_fk.up.sql`.
2. **Soft-delete refactor** (its own PR) — `deleted_at` columns + `soft_delete_by_id()` + `restore_by_id()` + filter every SELECT. See section 3 above for scope.
3. **Tighten `style-src`** — extract Bootstrap inline-style usages (animations, popovers) to either CSS classes or nonce-permitted blocks.
4. **Per-tenant currency** — make `$CURRENCY_CODE` a column in a settings table or `.env` value.
5. **Browser-level UI tests** — Playwright covering the login → add-product → add-sale → invoice happy path.
6. **Pre-commit hook** for `php -l` on staged files (the CI catches this on push but pre-commit prevents bad commits).
