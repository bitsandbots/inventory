const { test: setup, expect } = require('@playwright/test');
const path = require('path');

const BASE_URL = process.env.INVENTORY_BASE_URL || 'http://localhost:8080';
const ADMIN_AUTH = path.join(__dirname, '.auth', 'admin.json');
const USER_AUTH = path.join(__dirname, '.auth', 'user.json');

// Regular-user fixture. Username uses the HARNESS_ convention (matches
// existing PHP tests). Password satisfies validate_password(): ≥8 chars,
// letter + digit, not in deny list.
const TEST_USER = {
  fullName: 'HARNESS UI Test User',
  username: 'harness_uitest_user',
  password: 'Harness_uitest_pw_2026',
  level: '3', // ROLE_USER
};

setup('preflight: app server reachable', async ({ request }) => {
  const res = await request.get('/users/index.php');
  if (!res.ok()) {
    throw new Error(
      `UI tests require the app running at ${BASE_URL} — start Apache and retry.`
    );
  }
});

setup('authenticate as admin and save admin storageState', async ({ page }) => {
  await page.goto('/users/index.php');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/users/home.php', { timeout: 10_000 });

  const title = await page.title();
  if (title.toLowerCase().includes('login')) {
    throw new Error('auth.setup: admin login failed — check credentials and DB seed');
  }
  await page.context().storageState({ path: ADMIN_AUTH });
});

setup('seed HARNESS_ regular user and save user storageState', async ({ browser }) => {
  // Step 1: as admin, idempotently create the HARNESS_ user via /users/add_user.php
  const adminCtx = await browser.newContext({ storageState: ADMIN_AUTH });
  const adminPage = await adminCtx.newPage();

  await adminPage.goto('/users/users.php');
  const userRow = adminPage.locator(`tr:has-text("${TEST_USER.username}")`);
  if ((await userRow.count()) === 0) {
    await adminPage.goto('/users/add_user.php');
    await adminPage.fill('input[name="full-name"]', TEST_USER.fullName);
    await adminPage.fill('input[name="username"]', TEST_USER.username);
    await adminPage.fill('input[name="password"]', TEST_USER.password);
    await adminPage.selectOption('select[name="level"]', TEST_USER.level);
    await adminPage.click('button[name="add_user"]');
    await adminPage.waitForURL(/add_user\.php/);
  }

  // Step 2: as admin, idempotently add HARNESS_ user to the Default Organization (id=1).
  // add_member.php is harmless if the user is already a member (returns a
  // danger flash, no DB change), so we can POST unconditionally.
  await adminPage.goto('/orgs/edit_org.php?id=1');
  await adminPage.fill('form[action="add_member.php"] input[name="username"]', TEST_USER.username);
  await adminPage.selectOption('form[action="add_member.php"] select[name="role"]', 'member');
  await adminPage.click('form[action="add_member.php"] button[type="submit"]');
  await adminPage.waitForURL(/edit_org\.php/);
  await adminCtx.close();

  // Step 3: log in as the HARNESS_ user; save user.json
  const userCtx = await browser.newContext();
  const userPage = await userCtx.newPage();
  await userPage.goto('/users/index.php');
  await userPage.fill('input[name="username"]', TEST_USER.username);
  await userPage.fill('input[name="password"]', TEST_USER.password);
  await userPage.click('button[type="submit"]');
  await userPage.waitForURL('**/users/home.php', { timeout: 10_000 });

  const title = await userPage.title();
  if (title.toLowerCase().includes('login')) {
    throw new Error(
      `auth.setup: HARNESS_ user login failed — likely missing org_members row for ${TEST_USER.username}`
    );
  }
  await userCtx.storageState({ path: USER_AUTH });
  await userCtx.close();
});
