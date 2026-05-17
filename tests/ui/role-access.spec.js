const { test, expect } = require('@playwright/test');

// Uses the regular-user storageState (HARNESS_uitest_user, level=ROLE_USER).
// orgs.php is gated by page_require_level(ROLE_ADMIN) — a non-admin must be
// redirected away (currently to home.php via page_require_level()).
test.use({ storageState: 'tests/ui/.auth/user.json' });

test.describe('Role-gated access (regular user)', () => {
  test('regular user is redirected away from /orgs/orgs.php', async ({ page }) => {
    await page.goto('/orgs/orgs.php');
    // page_require_level() redirects non-admins; URL must not still be orgs.php
    await expect(page).not.toHaveURL(/\/orgs\/orgs\.php$/);
  });

  test('regular user does not see Organizations link in topbar', async ({ page }) => {
    await page.goto('/users/home.php');
    // The org-management link is rendered only for ROLE_ADMIN in the topbar.
    await expect(page.locator('a[href*="/orgs/orgs.php"]')).toHaveCount(0);
  });
});
