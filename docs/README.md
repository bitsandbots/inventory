# Inventory Management System — Documentation

A self-hosted, offline-capable inventory management system built with PHP and MySQL/MariaDB. Track products, manage stock levels, process sales orders, generate picklists and invoices, and run sales reports — all from a local Raspberry Pi or LAMP server.

## Project Purpose

- **Inventory tracking** — Products with SKU, location, quantity, pricing, categories, and images
- **Order management** — Create orders, add line-item sales, print invoices and picklists
- **Reporting** — Daily and monthly sales reports with date-range filtering
- **Multi-user RBAC** — Admin, Supervisor, and User roles with tiered permissions
- **Offline-first** — All assets bundled locally (no CDN), works on isolated networks
- **Security hardened** — bcrypt passwords, parameterized queries, CSRF protection on POST forms and state-changing GETs, session-fixation prevention, output escaping helpers

## Documentation Index

| Document | Description |
|----------|-------------|
| [Architecture](architecture.md) | Directory map, request lifecycle, data flow, RBAC model, key abstractions |
| [Tech Stack](tech-stack.md) | Languages, database, frontend libraries, security features, deployment target |
| [Setup & Usage](setup-and-usage.md) | Prerequisites, installation, configuration, daily workflows per role |
| [API & Components](api-components.md) | Core module reference: `MySqli_DB`, `Session`, CSRF, SQL helpers, auth flow, CRUD conventions |
| [Gap Analysis](gap-analysis.md) | Feature inventory: what exists vs. what's documented vs. what's missing, prioritized recommendations |

## Quick Navigation

- **New user?** Start with [Setup & Usage](setup-and-usage.md)
- **Developer?** Read [Architecture](architecture.md) then [API & Components](api-components.md)
- **Evaluating the project?** See [Tech Stack](tech-stack.md) and [Gap Analysis](gap-analysis.md)

## Offline Blueprint

For a single-file, browser-viewable overview of the entire system, open [`Blueprint_Overview.html`](../Blueprint_Overview.html) — no server required.
