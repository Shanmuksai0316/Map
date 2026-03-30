const { test, expect } = require('playwright/test');

test('playwright harness is configured and executable', async ({ page }) => {
  await page.goto(
    'data:text/html,' +
      encodeURIComponent(
        '<html><head><title>MapMars Smoke</title></head><body><h1 id="status">ready</h1></body></html>'
      )
  );

  await expect(page).toHaveTitle('MapMars Smoke');
  await expect(page.locator('#status')).toHaveText('ready');
});
