# Inventory

Inventory Management System with invoices, picklists, and sales reporting.

**Source**: https://github.com/bitsandbots/inventory

PHP 8.x + MariaDB application targeting self-hosted deployment on Raspberry Pi or any Apache + MySQL host. Offline-first (no CDN dependencies). Three-role access control: Admin / Supervisor / User.

---

## Quick start

```bash
bash install.sh
```

The installer detects PHP, MySQL, and Apache, creates the database from `schema.sql`, generates a `.env` with a strong `APP_SECRET`, creates a least-privilege MySQL app user, and wires up an Apache vhost on port 8080.

To wipe an existing deployment and reinstall fresh:

```bash
bash install.sh --reinstall
```

For manual install, troubleshooting, role-based workflows, and daily operations, see **[docs/setup-and-usage.md](docs/setup-and-usage.md)**.

---

## Default accounts

Default passwords are seeded into `schema.sql` and **must be changed on first login**.

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin` |
| Supervisor | `special` | `special` |
| User | `user` | `user` |

---

## Documentation

| Document | Audience |
|---|---|
| [Setup & Usage](docs/setup-and-usage.md) | Operators — install, daily workflows, troubleshooting |
| [Architecture](docs/architecture.md) | Developers — directory map, request lifecycle, RBAC model, schema |
| [Tech Stack](docs/tech-stack.md) | Developers — runtime versions, security features, deployment target |
| [API & Components](docs/api-components.md) | Developers — class methods, query helpers, CSRF helpers |
| [Gap Analysis](docs/gap-analysis.md) | Maintainers — known issues, recent fixes, next steps |

A standalone single-file offline reference: **[Blueprint_Overview.html](Blueprint_Overview.html)**

---

## Credits

Originally created by Siamon Hasan (2018-2020) using [PHP](http://php.net), [MySQL](https://www.mysql.com), and [Bootstrap](http://getbootstrap.com).

Enhanced by Cory J. Potter / CoreConduit Consulting Services. **v2.0 — 2026**: security hardening (bcrypt, prepared statements, CSRF on all forms and state-changing GETs, session-fixation prevention, output escaping); installer redesign with `--reinstall` flag; Apache vhost automation; least-privilege DB user provisioning.

---

## Support

[Contact](https://coreconduit.com/contact/) — [Donate](https://www.paypal.com/biz/fund?id=ZDR2NTBSKK7JE)
