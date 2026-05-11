# Tech Stack

## Server-Side

| Component | Version | Purpose |
|-----------|---------|---------|
| **PHP** | 8.x | Application language — procedural style with classes for DB, Session, and Media |
| **MariaDB** | 10.x | Relational database — 9 tables, InnoDB engine, foreign key constraints with CASCADE |
| **Apache** | 2.x | Web server with `.htaccess` directory protection and PHP module |

## Frontend (all bundled locally — zero CDN dependencies)

| Component | Version | Location | Purpose |
|-----------|---------|----------|---------|
| **Bootstrap** | 5.x | `libs/bootstrap/` | CSS framework for responsive layout, forms, tables, alerts, modals |
| **jQuery** | 3.x | `libs/js/jquery.min.js` | DOM manipulation, AJAX for product/customer autocomplete search |
| **Datepicker** | 1.x | `libs/datepicker/` | Bootstrap Datepicker for sales report date-range selection |
| **Main CSS** | — | `libs/css/main.css` | Custom application styles (sidebar, header, page layout) |

## Offline-First Design

- All CSS, JS, fonts, and icons are **bundled in the repository**
- No external CDN URLs in any `<link>` or `<script>` tag
- Works on **fully air-gapped networks** — no internet required
- jQuery (minified) is the only JavaScript framework; the rest is vanilla JS

## Security Features

| Feature | Implementation | File |
|---------|---------------|------|
| **Password hashing** | `password_hash(PASSWORD_BCRYPT)` with auto-upgrade of legacy SHA1 | `includes/sql.php` (`authenticate()`) |
| **SQL injection prevention** | `prepare_query()` with bound parameters (`?` placeholders) | `includes/database.php` |
| **CSRF protection** | Per-session token via `random_bytes(32)`, verified with `hash_equals()` | `includes/functions.php` |
| **Session hardening** | httponly, samesite=Lax, strict_mode, secure (when HTTPS) | `includes/load.php` |
| **Session fixation** | `session_regenerate_id(true)` on login | `includes/session.php` |
| **XSS prevention** | `h()` (htmlspecialchars wrapper) on all dynamic output | `includes/functions.php` |
| **Input sanitization** | `remove_junk()` pipeline: strip_tags → trim → stripslashes → htmlspecialchars | `includes/functions.php` |
| **Directory listing** | `.htaccess` files block indexing in `includes/`, `uploads/`, and project root | `.htaccess` files |
| **Activity logging** | All page requests logged with user_id, IP, action, timestamp | `includes/sql.php` (`logAction()`) |

## Deployment Target

- **Primary**: Raspberry Pi 5 running Apache + MariaDB (Raspberry Pi OS)
- **Also compatible**: Any Debian/Ubuntu LAMP stack
- **Memory**: Minimal — typical PHP memory_limit of 128M is sufficient
- **Storage**: Proportional to product images and sales history; database starts under 1MB

## Configuration

Configuration is via `.env` file in the project root (parsed by `includes/config.php`):

```
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=inventory
APP_SECRET=<random 64-char hex>
```

- `APP_SECRET` — used for CSRF token derivation (generate with `openssl rand -hex 32`)
- `.env` is git-ignored; `.env.example` is the committed template
- Constants defined: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `APP_SECRET`
- Currency code set in `load.php`: `$CURRENCY_CODE = 'USD'`
