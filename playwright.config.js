const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/ui',
  timeout: 30_000,
  workers: 1,
  use: {
    baseURL: process.env.INVENTORY_BASE_URL || 'http://localhost:8080',
    headless: true,
  },
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.js/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/ui/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
  ],
  reporter: 'line',
});
