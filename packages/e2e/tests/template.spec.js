const { test, expect } = require('@playwright/test');

test.describe('template example', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schv-template/');
    await page.waitForLoadState('networkidle');
  });

  test('page loads with heading', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Example: Template' })).toBeVisible();
  });

  test('user template substitutes placeholders', async ({ page }) => {
    const userPre = page.locator('pre').first();
    const content = await userPre.textContent();

    // Placeholders must be replaced
    expect(content).not.toContain('{name}');
    expect(content).not.toContain('{body}');

    // Fallback data values should appear
    expect(content).toContain('Alice');
    expect(content).toContain('Hello, I have a question.');
  });

  test('admin template substitutes placeholders', async ({ page }) => {
    const adminPre = page.locator('pre').nth(1);
    const content = await adminPre.textContent();

    expect(content).not.toContain('{name}');
    expect(content).not.toContain('{email}');
    expect(content).not.toContain('{body}');

    expect(content).toContain('Alice');
    expect(content).toContain('alice@example.com');
  });

  test('shows both user and admin template sections', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'User email' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Admin email' })).toBeVisible();
    await expect(page.locator('pre')).toHaveCount(2);
  });
});
