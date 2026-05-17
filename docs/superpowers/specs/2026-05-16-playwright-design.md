# Playwright UI Test Suite — Design Spec
**Date:** 2026-05-16
**Status:** Approved

## Goal

Add automated browser coverage for the two flows most at risk of silent regression: the login flow and the org-switcher golden path. The `switch_org.php` 500 (caught only via manual smoke) motivates this investment. All tests run locally on blueberry against `http://localhost:8080`.

---

## Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Runner language | JavaScript (native Playwright) | Node 20 available; JS is Playwright's first-class runtime |
| CI scope | Local-only for now | Browser + Apache + DB in Actions is a separate task |
| Repo location | `tests/ui/` + root-level config | Keeps all test types under `tests/`; config at root |
| Auth strategy | `globalSetup` storageState | Login once, reuse session — idiomatic Playwright |
| Test isolation | `HARNESS_` org, `beforeAll`/`afterAll` | Matches existing test convention; no production data pollution |

---

## File Layout

```
inventory/
├── package.json                  ← Playwright dep, "test:ui" script
├── playwright.config.js          ← baseURL, globalSetup, storageState default
├── tests/
│   ├── run.sh                    ← gains a Playwright block at the bottom
│   └── ui/
│       ├── .auth/
│       │   └── admin.json        ← gitignored; written by globalSetup at runtime
│       ├── globalSetup.js
│       ├── globalTeardown.js     ← stub (org cleanup is in afterAll hooks)
│       ├── login.spec.js
│       └── org-switcher.spec.js
```

`.gitignore` addition: `tests/ui/.auth/`

---

## globalSetup / storageState

`globalSetup.js` runs once before any spec:

1. Launch headless Chromium
2. Navigate to `http://localhost:8080/` (redirects to login)
3. Fill `username=admin`, `password=admin`, submit
4. Assert the resulting page title does **not** contain "Login"
5. Save `context.storageState()` → `tests/ui/.auth/admin.json`
6. Close browser

**Failure behaviour:** If the server is unreachable, throw with:
`"UI tests require the app running at http://localhost:8080 — start Apache and retry."`
This surfaces the failure immediately before any spec runs.

`globalTeardown.js` is a no-op stub. Org cleanup lives in spec `afterAll` hooks so it executes even when a test fails mid-suite.

---

## `login.spec.js`

Opts out of the default storageState to start unauthenticated:

```js
test.use({ storageState: { cookies: [], origins: [] } });
```

| Test | Action | Assertion |
|---|---|---|
| Valid credentials | Fill `admin`/`admin`, submit | URL leaves `/index.php`; authenticated element visible in topbar |
| Bad password | Fill `admin`/`wrongpassword`, submit | Inline error message visible on login page |
| Unknown username | Fill `nobody`/`anything`, submit | Same inline error assertion |

Disabled-account case is deferred — requires a HARNESS_ disabled user (future sprint).

---

## `org-switcher.spec.js`

Uses default admin storageState. Structured around a `beforeAll`/`afterAll` that manages a `HARNESS_` org.

**`beforeAll`:**
- Navigate to `/orgs/create.php`
- Fill org name `HARNESS_PlaywrightOrg`
- Submit; assert success redirect to org list

**Tests:**

| # | Test | Action | Assertion |
|---|---|---|---|
| 1 | Switcher appears with ≥2 orgs | Navigate to `/index.php` | Topbar org-switcher dropdown is visible |
| 2 | Checkmark on current org | Open switcher dropdown | "Default Organization" has active checkmark |
| 3 | Switch org | Click `HARNESS_PlaywrightOrg` in dropdown | Page reloads; topbar shows `HARNESS_PlaywrightOrg` as active |
| 4 | Switch back | Click "Default Organization" | Topbar reflects switch back |
| 5 | Delete org removes switcher | Navigate to `/orgs/`, delete `HARNESS_PlaywrightOrg`; navigate to `/index.php` | Org-switcher dropdown no longer visible |

**`afterAll` (safety net):** Navigate to `/orgs/`, delete any org whose name starts with `HARNESS_PlaywrightOrg`. Runs even if test 5 failed or was skipped.

---

## Integration with `tests/run.sh`

Added after the `SecurityHeadersTest` block. Reuses the existing `HTTP_OK` probe:

```bash
if [ "$HTTP_OK" -eq 1 ] && command -v npx &>/dev/null; then
    echo "--- Playwright UI Tests ---"
    if npx playwright test --reporter=line; then
        PASSED=$((PASSED + 1))
    else
        FAILED=$((FAILED + 1))
    fi
    TOTAL=$((TOTAL + 1))
else
    echo "--- Playwright UI Tests ---"
    echo "  SKIPPED: server not reachable or npx not found."
    SKIPPED=$((SKIPPED + 1))
    TOTAL=$((TOTAL + 1))
fi
```

---

## Out of scope (this sprint)

- CI integration (GitHub Actions Playwright job)
- Multi-user storageState (regular user role coverage)
- Core inventory golden path (product → order → sale)
- Disabled-account login test
