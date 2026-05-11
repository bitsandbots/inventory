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
├── users/                     # User & group administration
│   ├── index.php              # User dashboard / home page
│   ├── add_user.php           # Add user form+handler
│   ├── edit_user.php          # Edit user form+handler
│   ├── edit_account.php       # Edit own account
│   ├── change_password.php    # Change password form+handler
│   ├── add_group.php          # Add user group form+handler
│   ├── edit_group.php         # Edit user group form+handler
│   └── edit_category.php      # Edit group permissions
│
├── libs/                      # Bundled frontend assets (no CDN)
│   ├── bootstrap/             # Bootstrap 5 CSS/JS
│   ├── datepicker/            # Bootstrap Datepicker CSS/JS
│   ├── js/jquery.min.js       # jQuery 3.x
│   └── css/main.css           # Application styles
│
├── uploads/                   # User-uploaded media
│   ├── users/                 # User profile images
│   └── products/              # Product images
│
└── tests/                     # Test suite
    ├── run.sh                 # Test runner
    ├── bootstrap.php          # Test harness bootstrap
    ├── CSRFTest.php            # CSRF & helper function tests (unit)
    ├── AuthTest.php            # Authentication tests (integration)
    └── CRUDTest.php            # CRUD operation tests (integration)
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

## Database Schema (9 tables)

```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  categories  │    │   products   │    │    media     │
│──────────────│    │──────────────│    │──────────────│
│ id (PK)      │───▶│ category_id  │    │ id (PK)      │
│ name (UNQ)   │    │ id (PK)      │    │ file_name    │
└──────────────┘    │ name (UNQ)   │◀───│ file_type    │
                    │ sku          │    └──────────────┘
                    │ location     │
┌──────────────┐    │ quantity     │    ┌──────────────┐
│   orders     │    │ buy_price    │    │    sales     │
│──────────────│    │ sale_price   │    │──────────────│
│ id (PK)      │    │ media_id (FK)│    │ id (PK)      │
│ customer     │    │ category_id  │    │ order_id (FK)│
│ notes        │    │ date         │    │ product_id   │
│ paymethod    │    └──────────────┘    │ qty          │
│ date         │           │           │ price        │
└──────────────┘           │           │ date         │
                           │           └──────────────┘
┌──────────────┐           │
│    stock     │           │
│──────────────│           │
│ id (PK)      │           │
│ product_id   │◀──────────┘
│ quantity     │
│ comments     │
│ date         │
└──────────────┘

┌──────────────┐    ┌──────────────┐
│  user_groups │    │    users     │
│──────────────│    │──────────────│
│ id (PK)      │    │ id (PK)      │
│ group_name   │    │ name         │
│ group_level  │───▶│ username     │
│ group_status │    │ password     │
└──────────────┘    │ user_level   │
                    │ image        │
┌──────────────┐    │ status       │
│     log      │    │ last_login   │
│──────────────│    └──────────────┘
│ id (PK)      │
│ user_id (FK) │
│ remote_ip    │
│ action       │
│ date         │
└──────────────┘

Key relationships:
- products.category_id → categories.id (CASCADE)
- products.media_id → media.id
- sales.product_id → products.id (CASCADE)
- sales.order_id → orders.id
- stock.product_id → products.id
- users.user_level → user_groups.group_level (CASCADE)
```

## Key Abstractions

| Component | Type | File | Purpose |
|-----------|------|------|---------|
| `MySqli_DB` | Class | `includes/database.php` | DB connection, raw query, prepared CRUD (`prepare_query`, `prepare_select`, `prepare_select_one`) |
| `Session` | Class | `includes/session.php` | User login state, flash messaging, session ID regeneration |
| `Media` | Class | `includes/upload.php` | File upload validation, image processing for users and products |
| `csrf_token()` / `verify_csrf()` | Functions | `includes/functions.php` | CSRF token generation and POST validation |
| `h()` | Function | `includes/functions.php` | HTML output escaping shorthand |
| `remove_junk()` | Function | `includes/functions.php` | Input sanitization pipeline (strip tags, trim, htmlspecialchars) |
| `page_require_level()` | Function | `includes/sql.php` | RBAC gate — all protected pages call this |
| `authenticate()` | Function | `includes/sql.php` | Username/password verification with legacy SHA1 migration |
| `find_by_id()` / `find_all()` | Functions | `includes/sql.php` | Generic table CRUD helpers |
