# Documentation & Deployment Automation — Design Spec

**Date:** 2026-05-11
**Status:** Approved
**Project:** Inventory Management System (cc-inventory)

## Scope

Two-phase deliverable:
1. **Documentation bundle** — comprehensive `docs/` folder + synthesized `Blueprint_Overview.html` + gap analysis
2. **Deployment automation** — install script, systemd service, release script
3. **Cleanup** — remove stale files (`demo_inv.sql`, `migrations/001_quantity_int.sql`)
4. **Ship** — test, commit, tag release

## Phase 1: Documentation Bundle

### Files to create

| File | Purpose |
|------|---------|
| `docs/README.md` | Index/overview — project purpose, goals, navigation hub |
| `docs/architecture.md` | Directory map, request lifecycle, data flow diagram, RBAC model, key abstractions |
| `docs/tech-stack.md` | PHP 8.x, MariaDB 10.x, Bootstrap 5 (bundled), jQuery 3.x (bundled), security features, local-only design |
| `docs/setup-and-usage.md` | Prerequisites (LAMP), .env config, DB import (schema.sql), permissions, login, daily workflows per role |
| `docs/api-components.md` | Core module reference: MySqli_DB class, Session class, CSRF functions, SQL helpers, auth flow, CRUD conventions |
| `docs/gap-analysis.md` | Feature inventory: what exists vs. what's documented vs. what's missing, prioritized recommendations |
| `Blueprint_Overview.html` | Standalone single-file HTML with inline CSS synthesizing all docs; no external dependencies; browser-viewable offline |

### Design constraints

- All docs written in Markdown for version control
- Blueprint_Overview.html is standalone — inline CSS, no CDN links, no JS frameworks
- Gap analysis must trace each codebase module against documented claims
- README.md at project root updated to point to `docs/`

### Content sources

- Code analysis of 79 PHP files across 8 modules
- Existing README.md (setup instructions, security notes, defaults)
- schema.sql (9 tables, constraints, seed data)
- tests/ (3 test suites for behavioral verification)

## Phase 2: Deployment Automation

### Files to create

| File | Purpose |
|------|---------|
| `install.sh` | One-command setup: dependency check → DB creation → schema import → .env generation → permission fix |
| `scripts/inventory.service` | systemd unit file for Apache2 |
| `scripts/release.sh` | Git tag, tar.gz archive with checksum, ready for GitHub Releases |

### install.sh behavior

1. Detect PHP, MySQL/MariaDB, Apache availability
2. Prompt for DB credentials (or accept from env vars)
3. Create database if not exists
4. Import `schema.sql`
5. Generate `.env` from `.env.example` with APP_SECRET
6. Set uploads/ directory permissions
7. Output success summary with login credentials

### systemd service

- Type: simple, depends on mysql/mariadb
- Ensures Apache starts at boot for the inventory app
- (Note: Apache is the web server; inventory is served as a PHP application within Apache's document root, not a standalone daemon)

### release.sh behavior

1. Run test suite (tests/run.sh)
2. Bump version (prompt or accept arg)
3. Create git tag
4. Create tar.gz archive (excluding .git, tests, docs/superpowers)
5. Generate SHA256 checksum
6. Output release-ready filenames

## Phase 3: Cleanup

| File | Action | Rationale |
|------|--------|-----------|
| `demo_inv.sql` | Delete | Stale demo data; schema.sql is the canonical DB source |
| `migrations/001_quantity_int.sql` | Delete | One-off migration already applied in v2.0; not needed for fresh installs |
| `README.md` | Edit | Remove references to demo_inv.sql and old blog links; add pointer to docs/ |

## Phase 4: Ship

1. Run `bash tests/run.sh` — all suites must pass
2. `git add` all new/modified files
3. Commit with `docs: comprehensive documentation, deployment automation, and cleanup`
4. Tag as next patch version
5. Report ready state

## Risk Assessment

- **Low risk**: Documentation additions don't touch application code
- **Cleanup risk**: `demo_inv.sql` is still referenced in README — must update README simultaneously
- **install.sh risk**: Must use parameterized approach, never hardcode credentials; warn if overwriting existing .env

## Files NOT modified

- All PHP application files (79 files) — documentation only, no code changes
- `schema.sql` — unchanged
- `.env.example` — unchanged
- `tests/` — unchanged
- `.github/` — unchanged
- `layouts/`, `libs/` — unchanged
