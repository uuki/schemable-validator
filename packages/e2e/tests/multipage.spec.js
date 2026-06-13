const { test, expect } = require('@playwright/test');

const INPUT_URL    = '/schv-form-input/';
const CONFIRM_URL  = '/schv-form-confirm/';
const COMPLETE_URL = '/schv-form-complete/';

test.describe('multi-step form example', () => {
  test('step 1: shows input form', async ({ page }) => {
    await page.goto(INPUT_URL);
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('heading', { name: 'Step 1: Input' })).toBeVisible();
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('textarea[name="body"]')).toBeVisible();
  });

  test('step 1: shows validation errors for empty submit', async ({ page }) => {
    await page.goto(INPUT_URL);
    await page.waitForLoadState('networkidle');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Should stay on step 1 and show errors
    await expect(page).toHaveURL(new RegExp(INPUT_URL));
    await expect(page.locator('span[style*="color:red"]')).not.toHaveCount(0);
  });

  test('full happy path: input → confirm → complete', async ({ page }) => {
    // Step 1: fill and submit
    await page.goto(INPUT_URL);
    await page.waitForLoadState('networkidle');

    await page.fill('input[name="name"]', 'Alice');
    await page.fill('input[name="email"]', 'alice@example.com');
    await page.fill('textarea[name="body"]', 'Hello, I have a question.');
    await page.click('button[type="submit"]');
    await page.waitForURL(new RegExp(CONFIRM_URL), { timeout: 10_000 });

    // Step 2: verify data shown in confirmation
    await expect(page.getByRole('heading', { name: 'Step 2: Confirm' })).toBeVisible();
    await expect(page.getByText('Alice', { exact: true })).toBeVisible();
    await expect(page.getByText('alice@example.com')).toBeVisible();
    await expect(page.getByText('Hello, I have a question.')).toBeVisible();

    // Submit confirmation
    await page.click('button[type="submit"]');
    await page.waitForURL(new RegExp(COMPLETE_URL), { timeout: 10_000 });

    // Step 3: completion message
    await expect(page.getByRole('heading', { name: 'Step 3: Complete' })).toBeVisible();
    await expect(page.getByText('Thank you')).toBeVisible();
  });

  test('confirm: shows no-data message without session', async ({ page }) => {
    // Access confirm directly without going through input
    await page.goto(CONFIRM_URL);
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('No data')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Start over' })).toBeVisible();
  });

  test('back link on confirm returns to input', async ({ page }) => {
    await page.goto(INPUT_URL);
    await page.waitForLoadState('networkidle');

    await page.fill('input[name="name"]', 'Bob');
    await page.fill('input[name="email"]', 'bob@example.com');
    await page.fill('textarea[name="body"]', 'Test message');
    await page.click('button[type="submit"]');
    await page.waitForURL(new RegExp(CONFIRM_URL), { timeout: 10_000 });

    await page.getByRole('link', { name: '← Back' }).click();
    await page.waitForURL(new RegExp(INPUT_URL), { timeout: 10_000 });

    await expect(page.getByRole('heading', { name: 'Step 1: Input' })).toBeVisible();
  });

  test('complete: session cleared, confirm shows no-data after', async ({ page }) => {
    // Complete full flow
    await page.goto(INPUT_URL);
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="name"]', 'Carol');
    await page.fill('input[name="email"]', 'carol@example.com');
    await page.fill('textarea[name="body"]', 'Test');
    await page.click('button[type="submit"]');
    await page.waitForURL(new RegExp(CONFIRM_URL), { timeout: 10_000 });
    await page.click('button[type="submit"]');
    await page.waitForURL(new RegExp(COMPLETE_URL), { timeout: 10_000 });

    // Go back to confirm — session should be cleared
    await page.goto(CONFIRM_URL);
    await page.waitForLoadState('networkidle');
    await expect(page.getByText('No data')).toBeVisible();
  });
});
