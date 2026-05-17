# Playwright UI Test Suite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add automated browser tests for the login flow (3 cases) and org-switcher golden path (5 cases) against `http://localhost:8080`.

**Architecture:** Playwright JS with a single admin `storageState` written by `globalSetup.js`. Login tests opt out of saved state for a fresh unauthenticated context. The org-switcher spec creates and destroys a `HARNESS_PlaywrightOrg` in `beforeAll`/`afterAll`. Everything integrates into `tests/run.sh`.

**Tech Stack:** `@playwright/test` ^1.44, Node 20, Chromium (headless), no CI (local-only on blueberry).

---

## File Map

| Action | Path | Responsibility |
|---|---|---|
| Create | `package.json` | Playwright dep + `test:ui` script |
| Create | `playwright.config.js` | baseURL, globalSetup, default storageState |
| Create | `tests/ui/globalSetup.js` | Login once as admin, write `.auth/admin.json` |
| Create | `tests/ui/globalTeardown.js` | No-op stub |
| Create | `tests/ui/.auth/.gitkeep` | Track directory in git |
| Modify | `.gitignore` | Ignore `tests/ui/.auth/*.json` |
| Create | `tests/ui/login.spec.js` | 3 login flow tests (fresh context) |
| Create | `tests/ui/org-switcher.spec.js` | 5 org-switcher tests + HARNESS_ fixture |
| Modify | `tests/run.sh` | Add Playwright block after SecurityHeadersTest |

---

## Task 1: Bootstrap — package.json, config, .auth dir, .gitignore

**Files:**
- Create: `package.json`
- Create: `playwright.config.js`
- Create: `tests/ui/.auth/.gitkeep`
- Modify: `.gitignore`

- [ ] **Step 1: Create `package.json`**

```json
{
  "name": "inventory-ui-tests",
  "private": true,
  "scripts": {
    "test:ui": "playwright test",
    "test:ui:headed": "playwright test --headed"
  },
  "devDependencies": {
    "@playwright/test": "^1.44.0"
  }
}
```

- [ ] **Step 2: Create `playwright.config.js`**

```js
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/ui',
  timeout: 30_000,
  globalSetup: require.resolve('./tests/ui/globalSetup'),
  globalTeardown: require.resolve('./tests/ui/globalTeardown'),
  use: {
    baseURL: process.env.INVENTORY_BASE_URL || 'http://localhost:8080',
    storageState: 'tests/ui/.auth/admin.json',
    headless: true,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  reporter: 'line',
});
```

- [ ] **Step 3: Create `tests/ui/.auth/.gitkeep`** (empty file — keeps the directory in git)

```bash
mkdir -p tests/ui/.auth
touch tests/ui/.auth/.gitkeep
```

- [ ] **Step 4: Add `.auth` state files to `.gitignore`**

Append to `.gitignore`:
```
tests/ui/.auth/*.json
node_modules/
```

- [ ] **Step 5: Install Playwright and download Chromium**

```bash
npm install
npx playwright install chromium
```

Expected: Chromium downloads to `~/.cache/ms-playwright/`. No errors.

- [ ] **Step 6: Verify Playwright can enumerate tests (empty suite is OK)**

```bash
npx playwright test --list 2>&1 | head -5
```

Expected output contains: `No tests found` or lists 0 tests. If it errors on config, fix before continuing.

- [ ] **Step 7: Commit**

```bash
git add package.json playwright.config.js tests/ui/.auth/.gitkeep .gitignore
git commit -m "chore: bootstrap Playwright UI test suite"
```

---

## Task 2: globalSetup + globalTeardown

**Files:**
- Create: `tests/ui/globalSetup.js`
- Create: `tests/ui/globalTeardown.js`

- [ ] **Step 1: Create `tests/ui/globalSetup.js`**

```js
const { chromium } = require('@playwright/test');
const path = require('path');

const AUTH_FILE = path.join(__dirname, '.auth', 'admin.json');
const BASE_URL = process.env.INVENTORY_BASE_URL || 'http://localhost:8080';

module.exports = async function globalSetup() {
  // Fail fast with a clear message if the server isn't running.
  try {
    const res = await fetch(BASE_URL + '/users/index.php');
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
  } catch (e) {
    throw new Error(
      `UI tests require the app running at ${BASE_URL} — start Apache and retry.\n${e.message}`
    );
  }

  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();

  await page.goto(BASE_URL + '/users/index.php');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/users/home.php', { timeout: 10_000 });

  const title = await page.title();
  if (title.toLowerCase().includes('login')) {
    throw new Error('globalSetup: admin login failed — check credentials and DB seed');
  }

  await context.storageState({ path: AUTH_FILE });
  await browser.close();
};
```

- [ ] **Step 2: Create `tests/ui/globalTeardown.js`**

```js
// Org cleanup is handled in spec afterAll hooks — nothing to do here.
module.exports = async function globalTeardown() {};
```

- [ ] **Step 3: Verify globalSetup runs cleanly (requires localhost:8080 to be up)**

```bash
npx playwright test --list 2>&1
```

Expected: globalSetup runs (admin.json created), then `0 tests found` or test count listed. Check that `tests/ui/.auth/admin.json` exists:

```bash
ls -la tests/ui/.auth/
```

Expected: `admin.json` present with non-zero size.

- [ ] **Step 4: Commit**

```bash
git add tests/ui/globalSetup.js tests/ui/globalTeardown.js
git commit -m "test(ui): add Playwright globalSetup with admin storageState"
```

---

## Task 3: login.spec.js

**Files:**
- Create: `tests/ui/login.spec.js`

**Key selectors** (verified against `users/index.php` and `includes/session.php`):
- Username field: `input[name="username"]`
- Password field: `input[name="password"]`
- Submit: `button[type="submit"]`
- Error alert: `.alert-danger` (session `msg('d', …)` → key `danger`)
- Post-login URL: `/users/home.php`
- Authenticated indicator: `.profile .toggle span` (topbar username chip)

- [ ] **Step 1: Create `tests/ui/login.spec.js`**

```js
const { test, expect } = require('@playwright/test');

// Fresh unauthenticated context — login tests must not start logged in.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Login', () => {
  test('valid admin credentials redirect to home', async ({ page }) => {
    await page.goto('/users/index.php');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'admin');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/home\.php/);
    await expect(page.locator('.profile .toggle span')).toBeVisible();
  });

  test('wrong password stays on login with error', async ({ page }) => {
    await page.goto('/users/index.php');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');
    await expect(page.locator('.alert-danger')).toBeVisible();
    await expect(page).toHaveURL(/index\.php/);
  });

  test('unknown username stays on login with error', async ({ page }) => {
    await page.goto('/users/index.php');
    await page.fill('input[name="username"]', 'nobody');
    await page.fill('input[name="password"]', 'anything');
    await page.click('button[type="submit"]');
    await expect(page.locator('.alert-danger')).toBeVisible();
    await expect(page).toHaveURL(/index\.php/);
  });
});
```

- [ ] **Step 2: Run login tests**

```bash
npx playwright test tests/ui/login.spec.js --reporter=line
```

Expected:
```
  3 passed (Xs)
```

If a test fails, run with `--headed` to watch the browser:
```bash
npx playwright test tests/ui/login.spec.js --headed
```

- [ ] **Step 3: Commit**

```bash
git add tests/ui/login.spec.js
git commit -m "test(ui): add login flow spec — valid creds, bad password, unknown user"
```

---

## Task 4: org-switcher.spec.js

**Files:**
- Create: `tests/ui/org-switcher.spec.js`

**Key selectors** (verified against `layouts/header.php` and `orgs/orgs.php`):
- Create-org name field: `input[name="name"]` on `/orgs/orgs.php` (the create form, not the rename form — the create form has `input[type="hidden"][name="org_id"][value="0"]`)
- Create submit: `form:has(input[name="org_id"][value="0"]) button[type="submit"]`
- Org-switcher dropdown li: `li.dropdown` (only present with ≥2 orgs)
- Dropdown toggle: `li.dropdown > a.toggle`
- Switcher buttons: `.org-switcher-btn` (one per org)
- Active checkmark: `.glyphicon-ok` inside the active `.org-switcher-btn`
- Delete button in org row: `tr:has-text("HARNESS_PlaywrightOrg") button.btn-danger`
- Topbar org name: text inside `li.dropdown > a.toggle` (contains org name before the caret `<i>`)

**Flow notes:**
- Creating an org redirects to `edit_org.php?id=N` on success
- Deleting an org has `onclick="return confirm(...)"` — must accept the browser dialog before clicking
- Switching orgs submits a CSRF-protected POST form — Playwright handles this as a normal click

- [ ] **Step 1: Create `tests/ui/org-switcher.spec.js`**

```js
const { test, expect } = require('@playwright/test');

const HARNESS_ORG = 'HARNESS_PlaywrightOrg';

test.describe('Org switcher', () => {
  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    // Create the HARNESS_ org via the org management UI.
    await page.goto('/orgs/orgs.php');
    // The create form has a hidden org_id=0; the rename form has a non-zero org_id.
    await page.fill('form:has(input[name="org_id"][value="0"]) input[name="name"]', HARNESS_ORG);
    await page.click('form:has(input[name="org_id"][value="0"]) button[type="submit"]');
    // Success redirects to edit_org.php
    await expect(page).toHaveURL(/edit_org\.php/);

    await context.close();
  });

  test.afterAll(async ({ browser }) => {
    // Safety net: delete HARNESS_ org if it still exists (e.g. test 5 failed).
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto('/orgs/orgs.php');

    const harnessRow = page.locator('tr', { hasText: HARNESS_ORG });
    if (await harnessRow.count() > 0) {
      page.on('dialog', d => d.accept());
      await harnessRow.locator('button.btn-danger').click();
      await expect(page).toHaveURL(/orgs\.php/);
    }

    await context.close();
  });

  test('switcher dropdown appears with ≥2 orgs', async ({ page }) => {
    await page.goto('/users/home.php');
    await expect(page.locator('li.dropdown')).toBeVisible();
  });

  test('current org has active checkmark in dropdown', async ({ page }) => {
    await page.goto('/users/home.php');
    await page.click('li.dropdown > a.toggle');
    // The active org's button contains the checkmark glyphicon
    const activeBtn = page.locator('.org-switcher-btn', { has: page.locator('.glyphicon-ok') });
    await expect(activeBtn).toContainText('Default Organization');
  });

  test('switching to HARNESS_ org updates topbar', async ({ page }) => {
    await page.goto('/users/home.php');
    await page.click('li.dropdown > a.toggle');
    await page.click(`.org-switcher-btn:has-text("${HARNESS_ORG}")`);
    // Form submit reloads the page; wait for the URL to settle
    await page.waitForLoadState('networkidle');
    await expect(page.locator('li.dropdown > a.toggle')).toContainText(HARNESS_ORG);
  });

  test('switching back to Default Organization updates topbar', async ({ page }) => {
    // Start on home while HARNESS_ org may be active from previous test — switch either way
    await page.goto('/users/home.php');
    await page.click('li.dropdown > a.toggle');
    await page.click('.org-switcher-btn:has-text("Default Organization")');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('li.dropdown > a.toggle')).toContainText('Default Organization');
  });

  test('deleting HARNESS_ org hides the switcher', async ({ page }) => {
    await page.goto('/orgs/orgs.php');
    const harnessRow = page.locator('tr', { hasText: HARNESS_ORG });
    page.on('dialog', d => d.accept());
    await harnessRow.locator('button.btn-danger').click();
    await expect(page).toHaveURL(/orgs\.php/);

    await page.goto('/users/home.php');
    // With only one org left, the switcher dropdown should not be rendered
    await expect(page.locator('li.dropdown')).toHaveCount(0);
  });
});
```

- [ ] **Step 2: Run org-switcher tests**

```bash
npx playwright test tests/ui/org-switcher.spec.js --reporter=line
```

Expected:
```
  5 passed (Xs)
```

If a test fails with a timeout on a selector, run headed to inspect:
```bash
npx playwright test tests/ui/org-switcher.spec.js --headed --timeout=60000
```

- [ ] **Step 3: Run full Playwright suite to confirm both specs green**

```bash
npx playwright test --reporter=line
```

Expected:
```
  8 passed (Xs)
```

- [ ] **Step 4: Commit**

```bash
git add tests/ui/org-switcher.spec.js
git commit -m "test(ui): add org-switcher golden path spec — create, switch, delete"
```

---

## Task 5: Integrate into tests/run.sh

**Files:**
- Modify: `tests/run.sh`

The existing `run.sh` already probes `HTTP_OK` for `SecurityHeadersTest`. The Playwright block reuses that variable.

- [ ] **Step 1: Locate the insertion point**

Find this block near the bottom of `tests/run.sh` (just before the summary `===` block):

```bash
# HTTP-dependent test: only runs if a web server is reachable.
```

- [ ] **Step 2: Add the Playwright block after `SecurityHeadersTest`**

After the `run_test "tests/SecurityHeadersTest.php" ...` line, insert:

```bash
# UI tests (Playwright) — only if server is reachable and npx is available.
TOTAL=$((TOTAL + 1))
echo "--- Playwright UI Tests ---"
if [ "$HTTP_OK" -eq 1 ] && command -v npx &>/dev/null; then
    if npx playwright test --reporter=line 2>&1; then
        PASSED=$((PASSED + 1))
        echo ""
    else
        FAILED=$((FAILED + 1))
        echo "  (Playwright tests failed — run: npx playwright test --headed)"
        echo ""
    fi
else
    echo "  SKIPPED: server not reachable or npx not found."
    echo ""
    SKIPPED=$((SKIPPED + 1))
fi
```

- [ ] **Step 3: Run the full test suite and verify Playwright appears in summary**

```bash
bash tests/run.sh 2>&1 | tail -20
```

Expected summary line: `Playwright UI Tests` listed, and the overall summary shows 9/9 suites passed (8 PHP + 1 Playwright group).

- [ ] **Step 4: Commit**

```bash
git add tests/run.sh
git commit -m "test(ui): integrate Playwright into tests/run.sh"
```

---

## Self-review

**Spec coverage check:**
- ✅ Login valid creds → Task 3
- ✅ Login bad password → Task 3
- ✅ Login unknown user → Task 3
- ✅ Switcher appears ≥2 orgs → Task 4 test 1
- ✅ Checkmark on current org → Task 4 test 2
- ✅ Switch org, topbar updates → Task 4 test 3
- ✅ Switch back → Task 4 test 4
- ✅ Delete org hides switcher → Task 4 test 5
- ✅ HARNESS_ cleanup in afterAll → Task 4
- ✅ Server-unreachable failure message → Task 2 globalSetup
- ✅ tests/run.sh integration → Task 5
- ✅ `.gitignore` for auth state → Task 1

**No placeholders present.** All selectors are concrete and verified against the source files.

**Type consistency:** `HARNESS_ORG` constant used in `beforeAll`, `afterAll`, and test 3 — consistent.
