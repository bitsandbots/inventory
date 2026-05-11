# Setup & Usage

## Prerequisites

- **Web server**: Apache 2.x with `mod_rewrite` enabled
- **PHP**: 8.x with `mysqli`, `gd` (image manipulation), and `openssl` extensions
- **Database**: MariaDB 10.x or MySQL 8.x — empty database recommended
- **Disk**: ~100MB free for application + product images

## Installation

### Option 1: Automated (`install.sh`)

```bash
bash install.sh
```

The script will:
1. Detect PHP, MySQL/MariaDB, Apache availability
2. Prompt for database credentials
3. Create the database if it doesn't exist
4. Import `schema.sql`
5. Generate `.env` from `.env.example` with a secure `APP_SECRET`
6. Set `uploads/` directory permissions
7. Output success summary with login credentials

### Option 2: Manual

#### 1. Clone to Apache Document Root

```bash
cd /var/www/html
git clone https://github.com/bitsandbots/inventory.git inventory
cd inventory
```

#### 2. Database Setup

Start with a clean (empty) database, then import **schema.sql** to create the table structure:

```bash
mysql -u root -p < schema.sql
```

This creates 9 tables with constraints, indexes, and seed data (3 default users + 3 user groups).

#### 3. Configuration (.env)

Copy `.env.example` to `.env` and edit to match your database:

```bash
cp .env.example .env
```

Edit the file to set your database credentials:

```
DB_HOST=localhost
DB_USER=root
DB_PASS=your_mysql_password
DB_NAME=inventory
APP_SECRET=
```

Generate a random `APP_SECRET` (used for CSRF tokens):

```bash
openssl rand -hex 32
```

Paste that value into `.env` for `APP_SECRET`.

#### 4. Permissions

Ensure the `uploads/` directory and its subdirectories are writable by Apache:

```bash
sudo chmod -R 775 uploads/
sudo chown -R www-data:www-data uploads/
```

#### 5. Apache Configuration

Ensure the Apache site configuration allows `.htaccess` overrides (or enable them in the VirtualHost):

```apache
<Directory /var/www/html/inventory>
    AllowOverride All
</Directory>
```

Restart Apache:

```bash
sudo systemctl restart apache2
```

#### 6. Access

Navigate to `http://your-server/inventory/` — the login page should appear.

## Default Accounts

All passwords are bcrypt-hashed in the database:

| Role | Username | Password | Level |
|------|----------|----------|-------|
| Administrator | `admin` | `admin` | 1 |
| Supervisor | `special` | `special` | 2 |
| Default User | `user` | `user` | 3 |

**Important**: Change all default passwords immediately after first login via **Settings → Change Password**.

## Daily Workflows

### Administrator (Level 1)

1. **Manage users** — Navigate to Users → Add/Edit users and groups
2. **Manage products** — Add products, categories, stock adjustments
3. **Process sales** — Create orders, add sale line items, generate invoices
4. **Run reports** — Daily/monthly sales reports, date-range reports, stock reports
5. **Customer management** — Add/edit customers with contact and payment info

### Supervisor (Level 2)

1. **Stock management** — Add/edit products, adjust stock quantities
2. **Sales processing** — Create orders, add sales, print picklists and invoices
3. **Customer lookup** — View customer details for orders
4. **Reports** — Access sales and stock reports

### User (Level 3)

1. **View inventory** — Browse products by category, search by name/SKU
2. **Sales entry** — Add sales to existing orders
3. **View orders** — Look up order status and details

## Common Tasks

### Adding a Product

1. Navigate to **Products → Add Product**
2. Fill in: Name, SKU, Location, Quantity, Buy Price, Sale Price, Category
3. Upload product image (optional — PNG/JPG/GIF, handled by `Media` class)
4. Submit — product is created via prepared INSERT statement

### Creating a Sales Order

1. Navigate to **Sales → Add Order**
2. Select or search for customer (AJAX autocomplete from `includes/sql.php`)
3. Add sale line items by SKU or product search (AJAX-powered lookup)
4. Each sale reduces product quantity via `decrease_product_qty()`
5. Print **invoice** or **picklist** from the order detail page

### Running a Sales Report

**Date Range Report:**
1. Navigate to **Reports → Sales Report**
2. Select start and end dates (Bootstrap Datepicker widget)
3. Submit — results via `find_sale_by_dates()` showing per-product totals with buy/sell prices and profit calculation

**Daily Report:** Reports → Daily Sales → pick year/month
**Monthly Report:** Reports → Monthly Sales → pick year

### Managing User Access

1. Navigate to **Users → Groups** to define role tiers
2. Each group has: name, level (1-3), status (active/disabled)
3. Users are assigned a `user_level` that maps to a group
4. Disabling a group or user prevents login (checked in `page_require_level()`)

## Troubleshooting

| Symptom | Likely Cause | Fix |
|---------|-------------|-----|
| "Database connection failed" | Wrong credentials in `.env` | Verify DB_HOST, DB_USER, DB_PASS, DB_NAME |
| "Failed to select database" | Database doesn't exist | Run `mysql -u root -p < schema.sql` |
| White screen / no output | PHP error | Check Apache error log: `tail -f /var/log/apache2/error.log` |
| CSRF failures on forms | Expired session | Log out and log back in |
| Upload failures | Permission issue | `sudo chmod 775 uploads/products uploads/users` |
| No images display | Missing `no-image.png` | Ensure `media` table has row id=1 with file_name='no-image.png' |
| AJAX search not working | jQuery not loaded | Check browser console for 404s on `libs/js/jquery.min.js` |
