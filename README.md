# Inventory

Inventory Management System with invoices, picklists, and sales reporting.

**Source**: https://github.com/bitsandbots/inventory

## Documentation

Full documentation is in the [`docs/`](docs/README.md) directory:

| Document | Description |
|----------|-------------|
| [Architecture](docs/architecture.md) | Directory map, request lifecycle, RBAC model |
| [Tech Stack](docs/tech-stack.md) | PHP 8.x, MariaDB 10.x, Bootstrap 5, security features |
| [Setup & Usage](docs/setup-and-usage.md) | Installation, daily workflows per role |
| [API & Components](docs/api-components.md) | Core module reference for developers |
| [Gap Analysis](docs/gap-analysis.md) | Feature inventory, test coverage, recommendations |

A standalone offline blueprint is also available: [`Blueprint_Overview.html`](Blueprint_Overview.html)

## Quick Start (v2.0+)

### Automated

```bash
bash install.sh
```

### Manual

### 1. Database Setup

Import **schema.sql** to create the table structure (9 tables with indexes, constraints, and seed data):

```bash
mysql -u root -p < schema.sql
```

### 2. Configuration (.env)

```bash
cp .env.example .env
```

Edit `.env` to match your database credentials and generate an `APP_SECRET`:

```bash
openssl rand -hex 32
```

### 3. Permissions

```bash
sudo chmod -R 775 uploads/
sudo chown -R www-data:www-data uploads/
```

### 4. Login

Default accounts (bcrypt-hashed — change passwords immediately):

| Administrator | Supervisor | Default User |
|---|---|---|
| **Username**: admin | **Username**: special | **Username**: user |
| **Password**: admin | **Password**: special | **Password**: user |

### Security Notes (v2.0)

- Passwords stored using **bcrypt** (`PASSWORD_BCRYPT`). SHA1 hashes from older versions are automatically upgraded on first login.
- All database queries use **prepared statements** (parameterized) to prevent SQL injection.
- **CSRF protection** is enabled on all forms.
- All assets (Bootstrap, jQuery, Datepicker) are **bundled locally** — no CDN dependency for offline operation.
- Directory listings are disabled via `.htaccess` files.

## Support

[Contact Cory](https://coreconduit.com/contact/)

If you find this project useful...
[Donate](https://www.paypal.com/biz/fund?id=ZDR2NTBSKK7JE)

Enhanced by Cory J. Potter aka CoreConduit Consulting Services 2018 - 2020 (v2.0: 2026)

The application was initially created by Siamon Hasan, using [PHP](http://php.net),
[MySQL](https://www.mysql.com) and [Bootstrap](http://getbootstrap.com).
