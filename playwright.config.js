const htmlReportDir = process.env.PW_HTML_REPORT_DIR || 'playwright-report';
const outputDir = process.env.PW_OUTPUT_DIR || 'test-results';

// Root Playwright config for repository-level browser tests.
// Keeps Playwright isolated from React Native/Jest test files.
module.exports = {
  testDir: './playwright/tests',
  testMatch: '**/*.spec.js',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: [['list'], ['html', { outputFolder: htmlReportDir, open: 'never' }]],
  outputDir,
  use: {
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    headless: true,
  },
  projects: [
    {
      name: 'chromium',
      use: {
        browserName: 'chromium',
        viewport: { width: 1280, height: 720 },
      },
    },
  ],
};
