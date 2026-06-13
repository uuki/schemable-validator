const { test, expect } = require('@playwright/test');

test.describe('validate example', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schv-validate/');
    await page.waitForLoadState('networkidle');
  });

  test('page loads with heading', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Example: Validate' })).toBeVisible();
  });

  test('shows errors for empty submit', async ({ page }) => {
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // All four fields should fail
    const errors = page.locator('p[style*="color:red"]');
    await expect(errors).toHaveCount(4);
  });

  test('shows field-level error for invalid email', async ({ page }) => {
    await page.fill('input[name="name"]', 'Alice');
    await page.fill('input[name="email"]', 'not-an-email');
    await page.selectOption('select[name="type"]', 'general');
    await page.fill('textarea[name="body"]', 'Hello world');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const emailRow = page.locator('p[style*="color"]', { hasText: 'email' });
    await expect(emailRow).toHaveCSS('color', 'rgb(255, 0, 0)');
    const nameRow = page.locator('p[style*="color"]', { hasText: 'name' });
    await expect(nameRow).toHaveCSS('color', 'rgb(0, 128, 0)');
  });

  test('shows all valid for correct input', async ({ page }) => {
    await page.fill('input[name="name"]', 'Alice');
    await page.fill('input[name="email"]', 'alice@example.com');
    await page.selectOption('select[name="type"]', 'support');
    await page.fill('textarea[name="body"]', 'Hello world');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const validMarkers = page.locator('p[style*="color:green"]');
    await expect(validMarkers).toHaveCount(4);
    await expect(page.getByText('✓ valid').first()).toBeVisible();
  });

  test('repopulates form values after invalid submit', async ({ page }) => {
    await page.fill('input[name="name"]', 'Alice');
    await page.fill('input[name="email"]', 'bad');
    await page.selectOption('select[name="type"]', 'sales');
    await page.fill('textarea[name="body"]', 'Hello');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('input[name="name"]')).toHaveValue('Alice');
    await expect(page.locator('select[name="type"]')).toHaveValue('sales');
    await expect(page.locator('textarea[name="body"]')).toHaveValue('Hello');
  });
});
