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
