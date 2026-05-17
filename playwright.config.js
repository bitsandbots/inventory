const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/ui',
  timeout: 30_000,
  workers: 1,
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
