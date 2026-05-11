# API & Components

Core module reference for developers extending or maintaining the system.

## `MySqli_DB` Class — `includes/database.php`

Database abstraction layer. Instantiated as global `$db` at the end of the file.

### Properties

| Property | Visibility | Type | Purpose |
|----------|-----------|------|---------|
| `$con` | private | `mysqli` | Active MySQLi connection handle |
| `$query_id` | public | `mysqli_result|false` | Result from last `query()` call |

### Methods

```php
// Constructor — opens connection automatically
$db = new MySqli_DB();

// Raw query (SELECT/INSERT/UPDATE/DELETE) — escapes internally via real_escape_string
$result = $db->query("SELECT * FROM products WHERE id = 1");

// Prepared INSERT/UPDATE/DELETE — returns mysqli_stmt, dies on prepare failure
$stmt = $db->prepare_query(
    "UPDATE products SET quantity = quantity + ? WHERE id = ?",
    "ii",  $qty, $product_id
);

// Prepared SELECT — returns array of associative rows
$rows = $db->prepare_select(
    "SELECT * FROM products WHERE category_id = ?",
    "i", $category_id
);

// Prepared SELECT — returns single associative row or null
$row = $db->prepare_select_one(
    "SELECT * FROM users WHERE username = ? LIMIT 1",
    "s", $username
);

// Fetch helpers (wrap mysqli_* functions)
$row = $db->fetch_array($statement);    // mysqli_fetch_array
$obj = $db->fetch_object($statement);   // mysqli_fetch_object
$assoc = $db->fetch_assoc($statement);  // mysqli_fetch_assoc

// Result metadata
$count = $db->num_rows($statement);       // mysqli_num_rows
$new_id = $db->insert_id();               // mysqli_insert_id
$affected = $db->affected_rows();         // mysqli_affected_rows

// Utility
$safe = $db->escape($unsafe_string);       // mysqli_real_escape_string wrapper
$rows = $db->while_loop($result);          // Iterates result into array of rows
```

### Bind Types for `prepare_query()` / `prepare_select()`

| Type Char | PHP Type |
|-----------|----------|
| `"s"`     | string   |
| `"i"`     | integer  |
| `"d"`     | double   |

## `Session` Class — `includes/session.php`

Manages PHP session state. Instantiated as global `$session`.

### Usage

```php
// Check if user is logged in
if ($session->isUserLoggedIn()) { /* ... */ }

// Log in (regenerates session ID for fixation protection)
$session->login($user_id);  // Sets $_SESSION['user_id'], calls session_regenerate_id(true)

// Log out
$session->logout();  // Unsets $_SESSION['user_id']

// Flash message (survives one redirect)
$session->msg('s', 'Product saved successfully.');  // Types: d=danger, i=info, w=warning, s=success
$messages = $session->msg();  // Getter — returns array
```

### Global Messages

```php
global $msg;
// $msg is populated from $_SESSION['msg'] by flash_msg() in constructor
```

## CSRF Protection — `includes/functions.php`

All POST forms must include a CSRF token. The verification pattern is enforced by code review; callers must explicitly handle failure.

```php
// Generate token (idempotent — same token within a session)
$token = csrf_token();

// Output hidden input (use inside every <form method="post">)
echo csrf_field();
// → <input type="hidden" name="csrf_token" value="<64-char hex>">

// Verify in POST handler (returns bool)
if (!verify_csrf()) {
    // Handle rejection: redirect, error page, etc.
    $session->msg('d', 'Invalid CSRF token.');
    redirect('some_page.php', false);
}
// Proceed with POST handling...
```

**Note**: `verify_csrf()` returns `true` for non-POST requests (GET, HEAD, etc.) since side-effect-free methods don't need CSRF protection.

## Output Escaping — `includes/functions.php`

```php
// HTML-safe output — ALWAYS use on dynamic data in HTML context
echo h($user_input);  // htmlspecialchars($str, ENT_QUOTES, 'UTF-8')

// Full sanitization pipeline
$clean = remove_junk($dirty);
// → nl2br → trim → stripslashes → strip_tags → htmlspecialchars
```

## Data Access Layer — `includes/sql.php`

### Generic CRUD Helpers

```php
// Fetch all rows from a table
$rows = find_all('products');

// Fetch by raw SQL
$rows = find_by_sql("SELECT * FROM products WHERE sale_price > 50");

// Fetch single row by ID
$product = find_by_id('products', 5);  // Returns associative array or null

// Fetch single row by name column
$user = find_by_name('users', 'admin');  // Returns associative array or null

// Delete by ID
delete_by_id('products', $id);  // Returns true on success (affected_rows === 1)

// Check if table exists
if (tableExists('products')) { /* ... */ }

// Count rows
$row = count_by_id('products');  // Returns ['total' => N]
```

### Authentication

```php
// Authenticate — returns user row (with id, username, user_level) or false
$user = authenticate('admin', 'admin');

// Features:
//   - Bcrypt via password_verify()
//   - Legacy SHA1 auto-detection (40-char hex) → auto-rehash to bcrypt
//   - password_needs_rehash() check on every login
//   - Prepared statement — no SQL injection
```

### User Helpers

```php
// Get currently logged-in user (static cache — one DB query per request)
$user = current_user();  // Returns associative array from users table

// List all users with group names (JOIN)
$users = find_all_user();

// Update last_login timestamp
updateLastLogIn($user_id);  // Returns true on success

// Check group name uniqueness (returns true if name is available)
$available = find_by_groupName('NewGroup');

// Look up group by level
$group = find_by_groupLevel(2);  // Returns row with group_status
```

### RBAC Gate

```php
// Call at top of EVERY protected page
page_require_level($require_level);

// Checks, in order:
//   1. User logged in? → redirect to login
//   2. User account active? (status !== '0') → redirect with error
//   3. User's group active? (group_status !== '0') → redirect with error
//   4. User level ≤ required level? (lower = more privileged) → allow
//   5. Otherwise → "Sorry! you don't have permission."
```

### Product Queries

```php
// Full product listing (JOIN categories + media)
$products = join_product_table();

// Search products by name (AJAX autocomplete)
$names = find_product_by_title('widget');

// Search products by SKU (AJAX autocomplete)
$skus = find_product_by_sku('WDG-001');

// Full product search (name OR sku OR description) — AJAX autocomplete
$results = find_products_by_search('widget');

// Full product info by search (JOIN categories + media)
$products = find_all_product_info_by_search('widget');

// Products filtered by category
$products = find_products_by_category($category_id);

// Recent products (for dashboard)
$recent = find_recent_product_added(5);

// Full product info by name (AJAX lookup)
$info = find_all_product_info_by_title('Widget Pro');
```

### Stock Management

```php
// Increase product quantity (add stock)
increase_product_qty(50, $product_id);  // quantity = quantity + 50; returns true on success

// Decrease product quantity (sale)
decrease_product_qty(3, $product_id);   // quantity = quantity - 3; returns true on success
```

### Sales & Orders

```php
// All sales (JOIN products)
$all_sales = find_all_sales();

// All orders
$all_orders = find_all_orders();

// Sales for a specific order
$order_sales = find_sales_by_order_id($order_id);

// Recent sales (dashboard)
$recent = find_recent_sale_added(5);

// Highest selling products (dashboard)
$top = find_highest_selling_product(5);

// Date-range report (with totals: selling price, buying price, profit)
$report = find_sale_by_dates('2026-01-01', '2026-05-11');

// Daily sales breakdown (year + month)
$daily = dailySales(2026, 5);

// Monthly sales breakdown (year)
$monthly = monthlySales(2026);
```

## `Media` Class — `includes/upload.php`

Handles image uploads for products and user profiles.

```php
$media = new Media();

// Validate uploaded file
if ($media->upload($_FILES['file_upload'])) {
    // Process for product image
    $media->process_media();  // Move file → insert media row → return true

    // Or process for user profile
    $media->process_user($user_id);  // Move file → destroy old → update user row
}

// Check for errors
if (!empty($media->errors)) {
    foreach ($media->errors as $error) {
        echo h($error);
    }
}
```

**Allowed extensions**: gif, jpg, jpeg, png
**Upload paths**: `uploads/products/`, `uploads/users/`

## CRUD Convention

The system follows a consistent pattern for CRUD operations across all modules:

### Form Display

```php
// GET request — display the form
page_require_level(1);  // RBAC gate
include_once '../../layouts/header.php';
?>
<form method="post" action="edit_entity.php?id=<?php echo (int)$id; ?>">
    <?php echo csrf_field(); ?>
    <!-- form fields with h() escaping -->
</form>
<?php include_once '../../layouts/footer.php'; ?>
```

### POST Handler

```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $session->msg('d', 'Invalid CSRF token.');
        redirect('entities.php', false);
    }

    // Validate and sanitize input
    $name = remove_junk($_POST['name']);

    // Execute via prepared statement
    $stmt = $db->prepare_query(
        "UPDATE entities SET name = ? WHERE id = ?",
        "si", $name, $id
    );

    if ($stmt->affected_rows === 1) {
        $session->msg('s', 'Entity updated successfully.');
        redirect('entities.php', false);
    } else {
        $session->msg('d', 'Failed to update entity.');
        redirect("edit_entity.php?id=$id", false);
    }
}
```

## Currency Display — `includes/formatcurrency.php`

Format numeric values for display with the configured currency code (defined in `load.php` as `$CURRENCY_CODE = 'USD'`).
