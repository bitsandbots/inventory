# Setup & Usage

## Prerequisites

- **Web server**: Apache 2.x with `mod_rewrite` enabled
- **PHP**: 8.x with `mysqli`, `gd` (image manipulation), and `openssl` extensions
- **Database**: MariaDB 10.x or MySQL 8.x — empty database recommended
- **Disk**: ~100MB free for application + product images

## Installation

### Option 1: Automated (recommended)

```bash
bash install.sh
```

The script:
1. Detects PHP, MySQL/MariaDB, and Apache
2. Probes for admin DB access — prefers `sudo mysql` (unix_socket auth on Debian/Ubuntu MariaDB), falls back to user/password prompt
3. Creates the database from `schema.sql`
4. Creates a least-privilege application user `webuser@localhost` with only `SELECT/INSERT/UPDATE/DELETE` on the inventory database
5. Generates `.env` with a secure `APP_SECRET` (32 random bytes hex)
6. Adds execute (`o+x`) on the home directory if needed so Apache can traverse a symlinked DocumentRoot
7. Creates `/var/www/html/inventory` → project-root symlink
8. Adds `Listen 8080` to Apache `ports.conf` if missing
9. Writes Apache vhost config at `/etc/apache2/sites-available/inventory.conf` listening on port 8080
10. Enables the site (`a2ensite`) and reloads Apache after `configtest`

Access the app at `http://<server>:8080` (or `http://localhost:8080` from the host itself).

#### Reinstall (destructive)

To wipe the database, vhost, symlink, uploaded user content, and `.env`, then reinstall fresh:

```bash
bash install.sh --reinstall      # prompts: type 'RESET' to confirm
bash install.sh --reinstall -y   # non-interactive
```

The reinstall step preserves git-tracked files in `uploads/` (default placeholder images) — only user-uploaded content is removed. The current `.env` is backed up as `.env.bak.<timestamp>` before deletion.

### Option 2: Manual

1. **Clone the repo somewhere** — typically `/var/www/html/inventory`, or symlinked there from your dev tree.

2. **Create the database**:
   ```bash
   sudo mysql -e "CREATE DATABASE inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   sudo mysql inventory < schema.sql
   ```

3. **Create an app user**:
   ```bash
   sudo mysql -e "CREATE USER 'webuser'@'localhost' IDENTIFIED BY '';
                  GRANT SELECT, INSERT, UPDATE, DELETE ON inventory.* TO 'webuser'@'localhost';
                  FLUSH PRIVILEGES;"
   ```

4. **Write `.env`** (project root):
   ```
   DB_HOST=localhost
   DB_USER=webuser
   DB_PASS=
   DB_NAME=inventory
   APP_SECRET=<run: openssl rand -hex 32>
   ```

5. **Permissions on `uploads/`**:
   ```bash
   sudo chmod -R 775 uploads/
   sudo chown -R www-data:www-data uploads/
   ```

6. **Apache vhost** (`/etc/apache2/sites-available/inventory.conf`):
   ```apache
   <VirtualHost *:8080>
       ServerName inventory.local
       DocumentRoot /var/www/html/inventory
       <Directory /var/www/html/inventory>
           AllowOverride All
           Require all granted
           DirectoryIndex index.php
       </Directory>
       <FilesMatch "\.(env|sql|sh|md|gitignore)$">
           Require all denied
       </FilesMatch>
       ErrorLog ${APACHE_LOG_DIR}/inventory_error.log
   </VirtualHost>
   ```

   Then:
   ```bash
   echo "Listen 8080" | sudo tee -a /etc/apache2/ports.conf
   sudo a2ensite inventory.conf
   sudo apache2ctl configtest && sudo systemctl reload apache2
   ```

7. **If the DocumentRoot is a symlink into a home directory**, give Apache traversal access:
   ```bash
   chmod o+x ~
   ```

## Default Accounts

All passwords are bcrypt-hashed in the database (and auto-upgraded from legacy SHA1 on first login).

| Role | Username | Password | user_level |
|------|----------|----------|-----------:|
| Administrator | `admin` | `admin` | 1 |
| Supervisor | `special` | `special` | 2 |
| Default User | `user` | `user` | 3 |

**Change all three on first login** via **Settings → Change Password**.

## Daily Workflows

### Administrator (level 1)
- Manage users and groups (Users → Add/Edit)
- Manage products, categories, stock adjustments
- Process sales, generate invoices and picklists
- Run sales and stock reports
- Manage customers (contact, payment info)

### Supervisor (level 2)
- Add/edit products and adjust stock
- Create orders and sales, print invoices and picklists
- View customer details
- Access sales and stock reports

### Default User (level 3)
- Browse products by category, search by name/SKU
- Add sales to existing orders
- Look up order status

## Common Tasks

### Adding a product
1. **Products → Add Product**
2. Fill in: Name, SKU, Location, Quantity, Buy Price, Sale Price, Category
3. Optionally upload an image (PNG/JPG/GIF; handled by the `Media` class)
4. Submit — created via prepared `INSERT`

### Creating a sales order
1. **Sales → Add Order**
2. Search or select a customer (AJAX autocomplete from `includes/sql.php`)
3. Add line items by SKU or product search
4. Each sale decreases product quantity via `decrease_product_qty()`
5. Print invoice or picklist from the order detail page

### Running a sales report
- **Date range**: Reports → Sales Report → pick start/end → submit (`find_sale_by_dates()`)
- **Daily**: Reports → Daily Sales → pick year/month
- **Monthly**: Reports → Monthly Sales → pick year

### Managing user access
1. **Users → Groups** to define tiers; each group has name, level (1-3), status
2. Users get a `user_level` matching a group
3. Disabling a user (`status=0`) or group (`group_status=0`) blocks access via `page_require_level()`

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Browser shows `403 Forbidden` on `localhost:8080` | Apache can't traverse home dir to symlinked DocumentRoot | `chmod o+x ~` (execute-only, no list) |
| "Database connection failed. Please try again later." | `.env` has wrong creds, or `webuser` is missing | Re-check `.env`; recreate `webuser` per manual install step 3 |
| "Failed to select database" | Database doesn't exist | `sudo mysql inventory < schema.sql` |
| White screen / no output | PHP fatal error | `sudo tail -50 /var/log/apache2/inventory_error.log` |
| Login form accepts credentials but redirects right back to login | Old bug — header role check used string compare on int. Fixed in 2026-05-14 cycle. If reappears, check `layouts/header.php:74-82` | Cast to int: `(int)$user['user_level'] === 1` |
| CSRF "Invalid security token" on login | Stale tab / expired session | Refresh login page to fetch a new token |
| Upload failures | `uploads/` not writable by `www-data` | `sudo chown -R www-data:www-data uploads/` |
| Product image shows broken | Missing `no_image.jpg` placeholder | `git checkout HEAD -- uploads/` to restore tracked seeds |
| AJAX search not working | jQuery not loaded | Open browser console; look for 404s on `libs/js/jquery.min.js` |
| Apache reload silently skipped during install | Pre-2026-05-14 install.sh checked stdout but `apache2ctl configtest` writes to stderr | Update to current `install.sh` (`2>&1` before grep) |
