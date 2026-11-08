const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({
  testDir: './tests/browser',
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:9409',
    viewport: { width: 1280, height: 900 },
    launchOptions: { executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] },
  },
  webServer: { command: 'node tests/fixture-server.mjs', url: 'http://127.0.0.1:9409/test', reuseExistingServer: false },
});
