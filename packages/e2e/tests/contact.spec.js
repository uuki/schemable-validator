const { test, expect } = require('@playwright/test');

async function fillValid(page, overrides = {}) {
  const values = {
    name:  'Alice',
    email: 'alice@example.com',
    tel:   '09012345678',
    type:  'general',
    body:  'Hello, I have a question about your service.',
    ...overrides,
  };
  await page.fill('input[name="name"]',    values.name);
  await page.fill('input[name="email"]',   values.email);
  await page.fill('input[name="tel"]',     values.tel);
  await page.selectOption('select[name="type"]', values.type);
  await page.fill('textarea[name="body"]', values.body);
}

test.describe('contact form example', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schv-contact/');
    await page.waitForLoadState('networkidle');
  });

  test('page loads with heading', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Example: Contact Form' })).toBeVisible();
  });

  test('shows errors for all fields on empty submit', async ({ page }) => {
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const errors = page.locator('p[style*="color:red"]');
    await expect(errors).toHaveCount(5);
  });

  test('shows error for invalid email', async ({ page }) => {
    await fillValid(page, { email: 'not-an-email' });
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const emailRow = page.locator('p[style*="color"]', { hasText: 'email' });
    await expect(emailRow).toHaveCSS('color', 'rgb(255, 0, 0)');
    const nameRow = page.locator('p[style*="color"]', { hasText: 'name' });
    await expect(nameRow).toHaveCSS('color', 'rgb(0, 128, 0)');
  });

  test('shows error for invalid phone number', async ({ page }) => {
    await fillValid(page, { tel: '123' });
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const telRow = page.locator('p[style*="color"]', { hasText: 'tel' });
    await expect(telRow).toHaveCSS('color', 'rgb(255, 0, 0)');
  });

  test('shows error when body is too short', async ({ page }) => {
    await fillValid(page, { body: 'Short' });
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const bodyRow = page.locator('p[style*="color"]', { hasText: 'body' });
    await expect(bodyRow).toHaveCSS('color', 'rgb(255, 0, 0)');
  });

  test('shows error when type is not selected', async ({ page }) => {
    await fillValid(page);
    await page.selectOption('select[name="type"]', '');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const typeRow = page.locator('p[style*="color"]', { hasText: 'type' });
    await expect(typeRow).toHaveCSS('color', 'rgb(255, 0, 0)');
  });

  test('accepts phone with hyphens', async ({ page }) => {
    await fillValid(page, { tel: '090-1234-5678' });
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const telRow = page.locator('p[style*="color"]', { hasText: 'tel' });
    await expect(telRow).toHaveCSS('color', 'rgb(0, 128, 0)');
  });

  test('shows all valid for correct input', async ({ page }) => {
    await fillValid(page);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const validMarkers = page.locator('p[style*="color:green"]');
    await expect(validMarkers).toHaveCount(5);
  });

  test('repopulates values after invalid submit', async ({ page }) => {
    await fillValid(page, { email: 'bad-email' });
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('input[name="name"]')).toHaveValue('Alice');
    await expect(page.locator('input[name="tel"]')).toHaveValue('09012345678');
    await expect(page.locator('select[name="type"]')).toHaveValue('general');
    await expect(page.locator('textarea[name="body"]')).toHaveValue('Hello, I have a question about your service.');
  });
});
