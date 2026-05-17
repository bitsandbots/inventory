const { test, expect } = require('@playwright/test');

const HARNESS_ORG = 'HARNESS_PlaywrightOrg';

test.describe('Org switcher', () => {
  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext({ storageState: 'tests/ui/.auth/admin.json' });
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
    // Safety net: delete HARNESS_ org if it's still active (e.g. a test failed).
    const context = await browser.newContext({ storageState: 'tests/ui/.auth/admin.json' });
    const page = await context.newPage();
    await page.goto('/orgs/orgs.php');

    const harnessRow = page.locator('tr', { hasText: HARNESS_ORG });
    const deleteBtn = harnessRow.locator('button.btn-danger');

    // Only try to delete if the button exists (soft-deleted rows have a Restore button instead)
    if (await deleteBtn.count() > 0) {
      page.on('dialog', d => d.accept());
      await deleteBtn.click();
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

  test('deleting HARNESS_ org removes it from switcher', async ({ page }) => {
    // Verify HARNESS_ORG is in the switcher before deletion
    await page.goto('/users/home.php');
    await page.click('li.dropdown > a.toggle');
    await expect(page.locator(`.org-switcher-btn:has-text("${HARNESS_ORG}")`)).toBeVisible();

    // Close the dropdown
    await page.click('body');

    // Navigate to orgs page and delete the org
    await page.goto('/orgs/orgs.php');
    const harnessRow = page.locator('tr', { hasText: HARNESS_ORG });
    page.on('dialog', d => d.accept());
    await harnessRow.locator('button.btn-danger').click();
    await expect(page).toHaveURL(/orgs\.php/);

    // Navigate back to home and verify HARNESS_ORG is no longer in the switcher
    await page.goto('/users/home.php');
    // If switcher still exists, verify HARNESS_ORG is not in it
    const switcher = page.locator('li.dropdown');
    if (await switcher.count() > 0) {
      await page.click('li.dropdown > a.toggle');
      await expect(page.locator(`.org-switcher-btn:has-text("${HARNESS_ORG}")`)).not.toBeVisible();
    }
  });
});
