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
