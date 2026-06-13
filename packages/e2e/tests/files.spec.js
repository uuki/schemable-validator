const { test, expect } = require('@playwright/test');

test.describe('file validation example', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schv-files/');
    await page.waitForLoadState('networkidle');
  });

  test('page loads with heading', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Example: File Validation' })).toBeVisible();
    await expect(page.locator('input[type="file"]')).toBeVisible();
  });

  test('shows error for disallowed extension', async ({ page }) => {
    await page.locator('input[type="file"]').setInputFiles({
      name: 'document.txt',
      mimeType: 'text/plain',
      buffer: Buffer.from('hello'),
    });
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('p[style*="color:red"]')).toBeVisible();
  });

  test('shows success for jpg upload', async ({ page }) => {
    // Minimal JFIF JPEG (FF D8 FF E0 ... FF D9)
    const jpegBytes = Buffer.from([
      0xff, 0xd8, 0xff, 0xe0, 0x00, 0x10, 0x4a, 0x46, 0x49, 0x46, 0x00,
      0x01, 0x01, 0x00, 0x00, 0x01, 0x00, 0x01, 0x00, 0x00, 0xff, 0xd9,
    ]);
    await page.locator('input[type="file"]').setInputFiles({
      name: 'photo.jpg',
      mimeType: 'image/jpeg',
      buffer: jpegBytes,
    });
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('p[style*="color:green"]')).toBeVisible();
    await expect(page.getByText('✓ valid')).toBeVisible();
  });

  test('shows success for png upload', async ({ page }) => {
    // Minimal PNG (1x1 transparent)
    const pngBytes = Buffer.from([
      0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0x00, 0x00, 0x00,
      0x0d, 0x49, 0x48, 0x44, 0x52, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00,
      0x00, 0x01, 0x08, 0x02, 0x00, 0x00, 0x00, 0x90, 0x77, 0x53, 0xde,
      0x00, 0x00, 0x00, 0x0c, 0x49, 0x44, 0x41, 0x54, 0x08, 0xd7, 0x63,
      0xf8, 0xcf, 0xc0, 0x00, 0x00, 0x00, 0x02, 0x00, 0x01, 0xe2, 0x21,
      0xbc, 0x33, 0x00, 0x00, 0x00, 0x00, 0x49, 0x45, 0x4e, 0x44, 0xae,
      0x42, 0x60, 0x82,
    ]);
    await page.locator('input[type="file"]').setInputFiles({
      name: 'image.png',
      mimeType: 'image/png',
      buffer: pngBytes,
    });
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('p[style*="color:green"]')).toBeVisible();
    await expect(page.getByText('✓ valid')).toBeVisible();
  });
});
