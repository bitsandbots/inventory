# Gap Analysis

Feature inventory, test coverage, and prioritized recommendations.

## What Exists (Verified by Code Analysis)

| Capability | Status | Details |
|-----------|--------|---------|
| Product CRUD | **Complete** | Add, edit, view, delete with image upload, category assignment, SKU tracking |
| Category management | **Complete** | CRUD for product categories with uniqueness constraint |
| Customer CRUD | **Complete** | Name, address, contact info, payment method |
| Sales order creation | **Complete** | Multi-line-item orders with customer selection |
| Sale line-item management | **Complete** | Add via SKU/search/by-customer, edit qty/price, delete |
| Invoice generation | **Complete** | Printable HTML invoice (`sales_invoice.php`) |
| Picklist generation | **Complete** | Printable HTML picklist (`order_picklist.php`) |
| Stock adjustment | **Complete** | Add stock with comments, view stock history |
| Quantity tracking | **Complete** | Increase/decrease via `increase_product_qty()`/`decrease_product_qty()` |
| Sales reports (date range) | **Complete** | Date-range with totals, buy/sell price, profit calculation |
| Daily sales report | **Complete** | Year/month breakdown per product |
| Monthly sales report | **Complete** | Year breakdown per product |
| Stock report | **Complete** | Inventory quantity listing |
| User management | **Complete** | CRUD for users with image, status toggle |
| Group/RBAC management | **Complete** | 3-tier groups (Admin/Supervisor/User), status toggle |
| Password change | **Complete** | Self-service password change |
| Login authentication | **Complete** | bcrypt with legacy SHA1 auto-upgrade |
| Session management | **Complete** | httponly, samesite, strict_mode, secure flag, fixation protection |
| CSRF protection | **Complete** | Token generation via `random_bytes(32)`, `hash_equals()` verification |
| Output escaping | **Complete** | `h()` shorthand and `remove_junk()` pipeline on all user-facing output |
| Prepared statements | **Complete** | All new queries use `prepare_query()`/`prepare_select()` with bound params |
| Activity logging | **Complete** | Page-level user action logging to `log` table (skips static assets) |
| Offline asset bundling | **Complete** | Bootstrap, jQuery, Datepicker all in `libs/` — zero CDN dependencies |
| `.htaccess` protection | **Complete** | Directory listing disabled in `includes/`, `uploads/`, project root |
| Product search (AJAX) | **Complete** | By name, SKU, or combined search with category+media JOIN |
| Customer search (AJAX) | **Complete** | By name autocomplete |

## Test Coverage (3 Suites)

| Suite | Type | Tests | What's Covered |
|-------|------|-------|----------------|
| `CSRFTest.php` | Unit (no DB) | 10 tests | CSRF token generation, persistence, field output, verification (valid/wrong/missing), GET bypass, `h()` escaping, `remove_junk()`, `randString()` |
| `AuthTest.php` | Integration (requires DB) | 7 tests | Admin/special/user login, wrong password, non-existent user, `find_by_id()` user lookup, `find_by_name()` null handling |
| `CRUDTest.php` | Integration (requires DB) | 8 tests | `join_product_table()`, product INSERT, `find_by_id()`, `increase_product_qty()`, `decrease_product_qty()`, `delete_by_id()`, quantity column INT type verification, `find_sale_by_dates()` |

## What's Missing

### High Priority

| Gap | Impact | Recommendation |
|-----|--------|----------------|
| **No automated CI** | Regressions caught late | Add GitHub Actions workflow running `tests/run.sh` on push/PR |
| **No password complexity enforcement** | Weak passwords allowed | Add minimum length (8+) and character class validation in `change_password.php` |
| **No rate limiting on login** | Brute-force vulnerable | Add login attempt tracking per IP in `authenticate()` with exponential backoff |
| **No Content-Security-Policy header** | XSS defense depth | Emit CSP header in `layouts/header.php` or `.htaccess` |
| **`upload.php` uses direct string interpolation** | SQL injection surface | Refactor `update_userImg()` and `insert_media()` to use `prepare_query()` |
| **No test for `page_require_level()`** | Auth bypass risk | Add integration test for RBAC gate with all three levels |
| **No test for `authenticate()` SHA1 migration** | Legacy compat break | Add test verifying SHA1→bcrypt auto-upgrade path |
| **No test for session fixation protection** | Security gap | Verify session ID changes after `$session->login()` |

### Medium Priority

| Gap | Impact | Recommendation |
|-----|--------|----------------|
| No test for `increase_product_qty()`/`decrease_product_qty()` edge cases | Negative quantities | Test that quantity doesn't go below zero |
| No XSS test for rendered pages | XSS surface in 79 files | Add smoke test verifying `h()` is used on all echoed variables |
| `find_by_sql()` used for raw queries in `sql.php` | Inconsistent query style | Migrate remaining raw queries to `prepare_select()` (e.g., `find_recent_sale_added`, `find_recent_product_added`) |
| No data export capability | Data lock-in | Add CSV export for products, sales, and reports |
| No pagination on large lists | UX degradation at scale | Add LIMIT/OFFSET pagination on products, sales, and customers |
| Default passwords are trivial | Security for new installs | Force password change on first login |
| `demo_inv.sql` still referenced | Stale reference in docs | Remove from README (planned in Phase 3 cleanup) |

### Low Priority

| Gap | Impact | Recommendation |
|-----|--------|----------------|
| No product barcode support | Manual entry only | Add barcode field and scanner integration |
| No email notifications | Manual communication | Add order confirmation emails via PHP mail() |
| No audit diff (who changed what) | Limited audit trail | Enhance `log` table with old/new values for data changes |
| No unit/integration test distinction in runner | Clarity | Split test suites explicitly; add PHPUnit or Pest framework |
| No mobile-responsive verification test | Unknown mobile UX | Manual testing only; add viewport tests |
| Static currency code (`$CURRENCY_CODE`) | No multi-currency | Move to `.env` configuration |
| Incomplete inline documentation | Developer onboarding | Many older functions lack docblocks |

## Code Quality Notes

- **79 PHP files** across 8 modules — no framework, procedural style with 3 classes
- **Mix of query styles**: Prepared statements coexist with raw `query()` calls and string interpolation
- **`upload.php`** is the primary technical debt item — string interpolation in SQL statements bypasses the `prepare_query()` pattern
- **`config.php`** uses `define()` constants rather than a config class — adequate for current scope
- **Error handling**: `die()` on database errors (user-friendly message, detail in log) — acceptable for an internal tool but not ideal for API-style recovery

## Summary

The system is functionally complete for its target use case (small business inventory with orders and reporting). The most critical gaps are security hardening items (CSP headers, rate limiting, upload.php prepared statement migration) and test coverage for the authorization system. No application code is broken or missing — all documented features in the spec are implemented and working.
