const { defineConfig } = require('@playwright/test');

const BASE = 'http://127.0.0.1:9400';

module.exports = defineConfig({
  testDir: './tests',
  timeout: 30_000,
  workers: 1,
  retries: process.env.CI ? 2 : 0,
  globalSetup: './globalSetup.js',
  use: {
    baseURL: BASE,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
  },
});
