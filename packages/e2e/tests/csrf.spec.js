const { test, expect } = require('@playwright/test');

test.describe('CSRF token example', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schv-csrf/');
    await page.waitForLoadState('networkidle');
  });

  test('page loads and shows token', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Example: CSRF Token' })).toBeVisible();
    // Token code element should contain a 64-char hex string
    const tokenEl = page.locator('code');
    await expect(tokenEl).toBeVisible();
    const token = await tokenEl.textContent();
    expect(token).toMatch(/^[a-f0-9]{64}$/);
  });

  test('token in hidden field matches displayed token', async ({ page }) => {
    const displayed = await page.locator('code').textContent();
    const hidden = await page.locator('input[name="schv_csrf_token"]').inputValue();
    expect(displayed?.trim()).toBe(hidden);
  });

  test('submits successfully with valid token', async ({ page }) => {
    await page.fill('input[name="message"]', 'Hello');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('p[style*="color:green"]')).toBeVisible();
    await expect(page.getByText('✓ valid')).toBeVisible();
  });

  test('rejects submission with tampered token', async ({ page }) => {
    await page.fill('input[name="message"]', 'Hello');
    // Overwrite hidden field with invalid token
    await page.evaluate(() => {
      const el = document.querySelector('input[name="schv_csrf_token"]');
      if (el) el.value = 'a'.repeat(64);
    });
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Invalid CSRF token.')).toBeVisible();
  });
});
