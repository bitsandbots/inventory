# Architecture

## Directory Map

```
inventory/
├── index.php                  # Entry point / login page
├── .env.example               # Environment configuration template
├── .htaccess                  # Apache directory listing protection
├── schema.sql                 # Canonical database schema (9 tables)
├── Blueprint_Overview.html    # Standalone offline documentation
│
├── includes/                  # Core application engine
│   ├── load.php               # Bootstrap: constants, session hardening, autoload
│   ├── config.php             # .env loader → DB_HOST, DB_USER, DB_PASS, DB_NAME, APP_SECRET
│   ├── database.php           # MySqli_DB class (connection, query, prepared statements)
│   ├── session.php            # Session class (login/logout, flash messages)
│   ├── functions.php          # CSRF, output escaping (h), redirect, validation, helpers
│   ├── sql.php                # Data access layer (CRUD helpers, auth, product queries)
│   ├── upload.php             # Media class (image upload/validation for products & users)
│   └── formatcurrency.php     # Currency display formatting
│
├── layouts/                   # Shared UI shell
│   ├── header.php             # HTML <head>, top nav bar, user dropdown, sidebar router
│   ├── footer.php             # Closing tags, Bootstrap JS includes
│   ├── admin_menu.php         # Sidebar menu for Admin (level 1)
│   ├── special_menu.php       # Sidebar menu for Supervisor (level 2)
│   └── user_menu.php          # Sidebar menu for User (level 3)
│
├── customers/                 # Customer CRUD module
│   ├── customers.php          # List all customers
│   ├── add_customer.php       # Add new customer form+handler
│   └── edit_customer.php      # Edit existing customer form+handler
│
├── products/                  # Product & stock management
│   ├── products.php           # Product list with category filter
│   ├── add_product.php        # Add product form+handler
│   ├── edit_product.php       # Edit product form+handler
│   ├── view_product.php       # Product detail view
│   ├── product_search.php     # Search products by name/SKU/description
│   ├── categories.php         # Category list
│   ├── edit_category.php      # Add/edit category form+handler
│   ├── add_stock.php          # Add stock (increase quantity)
│   └── edit_stock.php         # View stock history for product
│
├── sales/                     # Order & sale processing
│   ├── sales.php              # All sales listing
│   ├── add_order.php          # Create new order
│   ├── edit_order.php         # Edit existing order (add/remove line items)
│   ├── orders.php             # All orders listing
│   ├── add_order_by_customer.php   # Create order for specific customer
│   ├── add_sale_to_order.php        # Add line item to existing order
│   ├── add_sale_by_sku.php          # Add sale by SKU lookup
│   ├── add_sale_by_search.php       # Add sale by product search
│   ├── edit_sale.php                # Edit sale line item
│   ├── delete_sale.php              # Delete sale line item
│   ├── sales_by_order.php           # View all sales for an order
│   ├── sales_invoice.php            # Printable invoice
│   └── order_picklist.php           # Printable picklist
│
├── reports/                   # Sales & stock reporting
│   ├── sales_report.php       # Date-range sales report form
│   ├── sale_report_process.php # Sales report results (by date range)
│   ├── daily_sales.php        # Daily sales breakdown (year/month)
│   ├── monthly_sales.php      # Monthly sales breakdown (year)
│   ├── stock_report.php       # Stock report form
│   └── stock_report_process.php # Stock report results
│
├── users/                     # User & group administration (24 files)
│   ├── index.php              # User dashboard / home page
│   ├── auth.php               # Login handler (CSRF + rate limit check)
│   ├── logout.php             # Session teardown
│   ├── add_user.php           # Add user form+handler
│   ├── edit_user.php          # Edit user form+handler
│   ├── edit_account.php       # Edit own account
│   ├── change_password.php    # Change password form+handler
│   ├── profile.php            # User profile view
│   ├── add_group.php          # Add user group form+handler
│   ├── edit_group.php         # Edit user group form+handler
│   ├── users.php              # User list
│   ├── group.php              # Group list
│   ├── delete_user.php        # Soft-delete a user
│   ├── delete_group.php       # Delete a user group
│   ├── settings.php           # Admin-only app settings (currency code, etc.)
│   ├── log.php                # Audit log viewer
│   ├── delete_log.php         # Clear log entries
│   ├── delete_log_by_ip.php   # Clear log entries by IP
│   ├── trash.php              # Soft-delete trash UI (Admin only)
│   ├── restore.php            # Restore a soft-deleted record
│   ├── purge.php              # Permanently delete a soft-deleted record
│   └── admin.php              # Admin dashboard
│
├── migrations/                # Numbered UP/DOWN SQL migration files
│   ├── 001_quantity_int       # quantity columns VARCHAR→INT
│   ├── 002_failed_logins      # Rate-limit tracking table
│   ├── 003_log_user_fk        # log.user_id ON DELETE SET NULL
│   ├── 004_settings_table     # App settings key/value store
│   └── 005–009_soft_delete    # deleted_at on 5 tables
│
├── packaging/sql/migrations/  # Tenancy migrations (feature/tenancy-schema branch)
│   └── 010–021_tenancy        # orgs, org_members, org_id on 7 tables
│
├── libs/                      # Bundled frontend assets (no CDN)
│   ├── bootstrap/             # Bootstrap 5 CSS/JS
│   ├── datepicker/            # Bootstrap Datepicker CSS/JS
│   ├── js/jquery.min.js       # jQuery 3.x
│   └── css/main.css           # Application styles (col-w-* classes, no inline styles)
│
├── uploads/                   # User-uploaded media
│   ├── users/                 # User profile images
│   └── products/              # Product images
│
└── tests/                     # Test suite (62 tests across 6 suites)
    ├── run.sh                 # Test runner
    ├── bootstrap.php          # Test harness bootstrap (ob_start, HARNESS_ prefix)
    ├── CSRFTest.php           # CSRF helpers (unit, 16 tests)
    ├── AuthTest.php           # Authentication (integration, 9 tests)
    ├── CRUDTest.php           # CRUD operations (integration, 11 tests)
    ├── SecurityHeadersTest.php # HTTP security headers (7 tests)
    ├── SettingsTest.php       # Settings class (integration, 6 tests)
    └── SoftDeleteTest.php     # Soft-delete lifecycle (integration, 13 tests)
```

## Request Lifecycle

Every page request follows this sequence:

```
HTTP Request
    │
    ▼
┌──────────────────────────────────────────────┐
│ 1. index.php / module page                   │
│    require_once 'includes/load.php'          │
└──────────────────────────────────────────────┘
    │
    ▼
┌──────────────────────────────────────────────┐
│ 2. includes/load.php (Bootstrap)             │
│    • Define SITE_ROOT, LIB_PATH_INC          │
│    • Session hardening (ini_set)             │
│    • Autoload: config → functions → session  │
│      → upload → database → sql → currency    │
│    • Initialize CSRF token                   │
│    • Log user action (skip static assets)    │
└──────────────────────────────────────────────┘
    │
    ▼
┌──────────────────────────────────────────────┐
│ 3. includes/config.php                       │
│    • Resolve CONFIG_ROOT from file location  │
│    • Parse .env → DB_HOST, DB_USER, DB_PASS, │
│      DB_NAME, APP_SECRET                     │
└──────────────────────────────────────────────┘
    │
    ▼
┌──────────────────────────────────────────────┐
│ 4. includes/session.php                      │
│    • session_start() (with strict_mode)      │
│    • Session class: login/logout/flash msg   │
└──────────────────────────────────────────────┘
    │
    ▼
┌──────────────────────────────────────────────┐
│ 5. includes/database.php                     │
│    • MySqli_DB constructor → db_connect()    │
│    • $db global instance available           │
└──────────────────────────────────────────────┘
    │
    ▼
┌──────────────────────────────────────────────┐
│ 6. Module page (e.g., products/products.php) │
│    • page_require_level(N) — RBAC gate       │
│    • Handle POST (CSRF verify)               │
│    • Query data via sql.php helpers          │
│    • Include layouts/header.php              │
│    • Render HTML with h() escaping           │
│    • Include layouts/footer.php              │
└──────────────────────────────────────────────┘
```

## Authentication & RBAC

### User Levels

| Level | Role | Description |
|-------|------|-------------|
| 1 | Admin | Full access: all CRUD, user/group management, reports |
| 2 | Supervisor | Mid-tier: product/stock management, sales processing, reports |
| 3 | User | Limited: view products, basic sales entry |

### Auth Flow

1. **Login** — `authenticate($username, $password)` in `sql.php`:
   - Queries `users` table by username (prepared statement)
   - BCrypt: `password_verify($password, $stored_hash)`
   - Legacy SHA1: detected by 40-char hex format, auto-rehashed to bcrypt
   - On success: `$session->login($user_id)` regenerates session ID (fixation protection)
   - `updateLastLogIn($user_id)` records timestamp

2. **Page-level authorization** — `page_require_level($require_level)` in `sql.php`:
   - Checks `$session->isUserLoggedIn()` → redirect to login
   - Checks user `status` (active/disabled)
   - Checks group `group_status` (active/disabled)
   - Checks `$current_user['user_level'] <= $require_level` (lower number = higher privilege)
   - On failure: flash message + redirect

3. **Session security** (enforced in `load.php` before `session_start()`):
   - `session.cookie_httponly = 1`
   - `session.cookie_samesite = 'Lax'`
   - `session.use_strict_mode = 1`
   - `session.cookie_secure = 1` (when HTTPS)
   - Session ID regeneration on login (prevents session fixation)

4. **CSRF protection** — All POST forms include `csrf_field()`, verified at handler entry.

### Menu Routing

`layouts/header.php` conditionally includes the correct sidebar menu based on `$user['user_level']`:
- Level 1 → `admin_menu.php`
- Level 2 → `special_menu.php`
- Level 3 → `user_menu.php`

## Database Schema (14 tables)

Five business tables (`users`, `customers`, `sales`, `orders`, `stock`) have `deleted_at TIMESTAMP` and `deleted_by INT` for the soft-delete pattern. Tables marked *(tenancy)* are on the `feature/tenancy-schema` branch.

| Table | Purpose | Soft-delete |
|-------|---------|------------|
| `categories` | Product categories | No |
| `products` | Product catalog | No (gap — see gap-analysis.md) |
| `media` | Product/user images | No |
| `orders` | Sales orders | Yes |
| `sales` | Order line items | Yes |
| `stock` | Stock adjustments | Yes |
| `customers` | Customer directory | Yes |
| `users` | Login accounts | Yes |
| `user_groups` | RBAC group definitions | No |
| `log` | Activity audit trail | No (log.user_id → users.id ON DELETE SET NULL) |
| `failed_logins` | Login rate-limit tracking | No (pruned probabilistically) |
| `settings` | App config key/value | No |
| `orgs` *(tenancy)* | Organization registry | Planned soft-delete |
| `org_members` *(tenancy)* | User ↔ org membership + role | No |

```
Key relationships:
- products.category_id → categories.id (CASCADE)
- products.media_id    → media.id
- sales.product_id     → products.id (CASCADE)
- sales.order_id       → orders.id
- stock.product_id     → products.id
- users.user_level     → user_groups.group_level (CASCADE)
- log.user_id          → users.id ON DELETE SET NULL
- org_members.org_id   → orgs.id (tenancy)
- org_members.user_id  → users.id (tenancy)
```

## Soft-Delete Pattern

Five tables use reversible soft-delete. The helpers live in `includes/sql.php`.

```
soft_delete_by_id($table, $id)         Stamps deleted_at = NOW(), deleted_by = session user
restore_by_id($table, $id)             Clears both columns (NOT NULL → NULL)
purge_by_id($table, $id)              Hard DELETE — only allowed when deleted_at IS NOT NULL
find_by_id_with_deleted($table, $id)  Bypasses filter (trash UI)
find_with_deleted($table)             Returns all rows including soft-deleted
```

`find_all()` and `find_by_id()` automatically add `WHERE deleted_at IS NULL` when `table_has_soft_delete()` returns true. The probe result is cached per request in a static array, so there is exactly one `SHOW COLUMNS` query per table per request.

The trash UI (`users/trash.php`) is Admin-only (level 1). `users/restore.php` and `users/purge.php` handle the two actions.

## Key Abstractions

| Component | Type | File | Purpose |
|-----------|------|------|---------|
| `MySqli_DB` | Class | `includes/database.php` | DB connection, raw query, prepared CRUD (`prepare_query`, `prepare_select`, `prepare_select_one`) |
| `Session` | Class | `includes/session.php` | User login state, flash messaging, session ID regeneration |
| `Media` | Class | `includes/upload.php` | File upload validation, image processing for users and products |
| `csrf_token()` / `verify_csrf()` / `verify_get_csrf()` / `csrf_url_param()` | Functions | `includes/functions.php` | CSRF token generation, POST validation, and GET-based delete link protection |
| `h()` | Function | `includes/functions.php` | HTML output escaping shorthand |
| `remove_junk()` | Function | `includes/functions.php` | Input sanitization pipeline (strip tags, trim, htmlspecialchars) |
| `page_require_level()` | Function | `includes/sql.php` | RBAC gate — all protected pages call this |
| `authenticate()` | Function | `includes/sql.php` | Username/password verification with legacy SHA1 migration |
| `find_by_id()` / `find_all()` | Functions | `includes/sql.php` | Generic table CRUD helpers |
