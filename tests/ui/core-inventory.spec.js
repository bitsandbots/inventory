const { test, expect } = require('@playwright/test');

// Uses default admin storageState (admin can manage categories/products/stock).
//
// Category is stable & reused across runs: delete_category.php is blocked by
// find_products_by_category() which includes soft-deleted rows, so a fresh
// category-per-run would accumulate undeletable leftovers.
const HARNESS_CAT = 'HARNESS_PwCat';
const HARNESS_PRODUCT = 'HARNESS_PwProduct_' + Date.now();
const INITIAL_QTY = 100;
const STOCK_ADDITION = 25;

async function readProductStock(page, productName) {
  await page.goto('/products/products.php');
  const row = page.locator('tbody tr', { hasText: productName });
  await expect(row).toHaveCount(1);
  // Columns: 1=Product 2=Photo 3=SKU 4=Category 5=Location 6=Stock
  return parseInt((await row.locator('td').nth(5).innerText()).trim(), 10);
}

async function ensureCategoryExists(page, name) {
  await page.goto('/products/categories.php');
  // Exact-text td match: leftover "HARNESS_PwCat_<ts>" rows would substring-match
  // hasText, so use :text-is() to require the exact name.
  const exists = page.locator(`tbody td:text-is("${name}")`);
  if ((await exists.count()) === 0) {
    await page.fill('input[name="category-name"]', name);
    await page.click('button[name="add_cat"]');
    await page.waitForURL(/categories\.php/);
  }
}

test.describe('Core inventory: product create + stock add', () => {
  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext({ storageState: 'tests/ui/.auth/admin.json' });
    const page = await ctx.newPage();
    page.on('dialog', d => d.accept());

    // Reap any leftover HARNESS_PwProduct_* rows from prior runs (bounded loop).
    await page.goto('/products/products.php');
    for (let i = 0; i < 10; i++) {
      const leftover = page.locator('tbody tr', { hasText: /HARNESS_PwProduct_/ });
      if ((await leftover.count()) === 0) break;
      await leftover.first().locator('a.btn-danger').click();
      await page.waitForLoadState('networkidle');
    }

    await ensureCategoryExists(page, HARNESS_CAT);

    // Create the HARNESS_ product with the stable category.
    await page.goto('/products/add_product.php');
    await page.fill('input[name="product-title"]', HARNESS_PRODUCT);
    await page.fill('input[name="product-desc"]', 'Playwright test product');
    await page.fill('input[name="product-sku"]', 'PWSKU-' + Date.now());
    await page.fill('input[name="product-location"]', 'PW-AISLE');
    await page.selectOption('select[name="product-category"]', { label: HARNESS_CAT });
    await page.fill('input[name="product-quantity"]', String(INITIAL_QTY));
    await page.fill('input[name="cost-price"]', '5');
    await page.fill('input[name="sale-price"]', '25');
    await page.click('button[name="add_product"]');
    await page.waitForURL(/\/products\/products\.php/);

    await ctx.close();
  });

  test.afterAll(async ({ browser }) => {
    const ctx = await browser.newContext({ storageState: 'tests/ui/.auth/admin.json' });
    const page = await ctx.newPage();
    page.on('dialog', d => d.accept());

    // Soft-delete only the product. Category is stable; intentionally left.
    await page.goto('/products/products.php');
    const prodRow = page.locator('tbody tr', { hasText: HARNESS_PRODUCT });
    if ((await prodRow.count()) > 0) {
      await prodRow.locator('a.btn-danger').click();
      await page.waitForLoadState('networkidle');
    }

    await ctx.close();
  });

  test('new product appears in the products list', async ({ page }) => {
    await page.goto('/products/products.php');
    await expect(page.locator('tbody tr', { hasText: HARNESS_PRODUCT })).toHaveCount(1);
  });

  test('add_stock increases product quantity', async ({ page }) => {
    const before = await readProductStock(page, HARNESS_PRODUCT);
    expect(before).toBe(INITIAL_QTY);

    await page.goto('/products/products.php');
    const row = page.locator('tbody tr', { hasText: HARNESS_PRODUCT });
    const href = await row.locator('a').first().getAttribute('href');
    const productId = href.match(/id=(\d+)/)[1];

    await page.goto(`/products/add_stock.php?id=${productId}`);
    await page.selectOption('select[name="product_id"]', productId);
    await page.fill('input[name="quantity"]', String(STOCK_ADDITION));
    await page.fill('input[name="comments"]', 'PW stock add');
    await page.click('button[name="add_stock"]');
    await page.waitForURL(/stock\.php/);

    const after = await readProductStock(page, HARNESS_PRODUCT);
    expect(after).toBe(INITIAL_QTY + STOCK_ADDITION);
  });
});
