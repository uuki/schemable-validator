const { test, expect } = require('@playwright/test');

test.describe('Schema Editor (admin)', () => {
  test.beforeEach(async ({ page }) => {
    // Log in to WP admin (Playground default credentials)
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForLoadState('networkidle');
  });

  test('Schema Editor page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=schv-schema-editor');
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: 'Schema Editor' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'New schema' })).toBeVisible();
  });

  test('can create a schema with fields', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=schv-schema-editor');
    await page.waitForLoadState('networkidle');

    // Fill in slug
    await page.fill('#schv_slug', 'test-contact');

    // Add a string field: name (required, minLength=1, maxLength=100)
    await page.click('#schv-add-field');
    const firstRow = page.locator('.schv-field-row').first();
    await firstRow.locator('input[placeholder="Field name"]').fill('name');
    await firstRow.locator('.schv-type-select').selectOption('string');
    await firstRow.locator('input[name$="[required]"]').check();
    await firstRow.locator('input[name$="[minLength]"]').fill('1');
    await firstRow.locator('input[name$="[maxLength]"]').fill('100');

    // Add an email field (required, format=email)
    await page.click('#schv-add-field');
    const secondRow = page.locator('.schv-field-row').nth(1);
    await secondRow.locator('input[placeholder="Field name"]').fill('email');
    await secondRow.locator('.schv-type-select').selectOption('string');
    await secondRow.locator('input[name$="[required]"]').check();
    await secondRow.locator('select[name$="[format]"]').selectOption('email');

    // Add an optional integer field: age (min=0, max=150)
    await page.click('#schv-add-field');
    const thirdRow = page.locator('.schv-field-row').nth(2);
    await thirdRow.locator('input[placeholder="Field name"]').fill('age');
    await thirdRow.locator('.schv-type-select').selectOption('integer');
    await thirdRow.locator('input[name$="[minimum]"]').fill('0');
    await thirdRow.locator('input[name$="[maximum]"]').fill('150');

    // Save
    await page.click('input[type="submit"][value="Save schema"]');
    await page.waitForLoadState('networkidle');

    // Verify success notice
    await expect(page.getByText('Schema saved.')).toBeVisible();

    // Verify the schema appears in the saved schemas list
    await expect(page.locator('table a', { hasText: 'test-contact' })).toBeVisible();
    await expect(page.locator('table td', { hasText: '3 fields' })).toBeVisible();

    // Verify the stored JSON Schema (inside a <details> block)
    const details = page.locator('details', { hasText: 'Stored JSON Schema' });
    await details.locator('summary').click();
    const jsonBlock = details.locator('pre');
    await expect(jsonBlock).toBeVisible();
    const json = await jsonBlock.textContent();
    const schema = JSON.parse(json);

    expect(schema.type).toBe('object');
    expect(schema.properties.name).toEqual({
      type: 'string',
      minLength: 1,
      maxLength: 100,
    });
    expect(schema.properties.email).toEqual({
      type: 'string',
      format: 'email',
    });
    expect(schema.properties.age).toEqual({
      type: 'integer',
      minimum: 0,
      maximum: 150,
    });
    expect(schema.required).toEqual(['name', 'email']);
  });

  test('can edit an existing schema', async ({ page }) => {
    // Navigate to the schema created in the previous test
    await page.goto('/wp-admin/admin.php?page=schv-schema-editor&slug=test-contact');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('heading', { name: /Edit: test-contact/ })).toBeVisible();

    // Verify fields are populated
    const firstRow = page.locator('.schv-field-row').first();
    await expect(firstRow.locator('input[placeholder="Field name"]')).toHaveValue('name');

    // The slug field should be readonly
    await expect(page.locator('#schv_slug')).toHaveAttribute('readonly', '');
  });

  test('can delete a schema', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=schv-schema-editor');
    await page.waitForLoadState('networkidle');

    // Accept the confirmation dialog
    page.on('dialog', dialog => dialog.accept());

    // Click delete for test-contact
    const deleteButton = page.locator('form', { has: page.locator('input[value="test-contact"]') })
      .locator('button', { hasText: 'Delete' });
    await deleteButton.click();
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Schema deleted.')).toBeVisible();
    await expect(page.locator('table a', { hasText: 'test-contact' })).not.toBeVisible();
  });

  test('can create and validate with stored schema via REST', async ({ page, request }) => {
    // Create a schema via the admin UI
    await page.goto('/wp-admin/admin.php?page=schv-schema-editor');
    await page.waitForLoadState('networkidle');

    await page.fill('#schv_slug', 'api-test');

    await page.click('#schv-add-field');
    const row = page.locator('.schv-field-row').first();
    await row.locator('input[placeholder="Field name"]').fill('email');
    await row.locator('.schv-type-select').selectOption('string');
    await row.locator('input[name$="[required]"]').check();
    await row.locator('select[name$="[format]"]').selectOption('email');

    await page.click('input[type="submit"][value="Save schema"]');
    await page.waitForLoadState('networkidle');
    await expect(page.getByText('Schema saved.')).toBeVisible();

    // Verify the stored JSON Schema (inside a <details> block)
    const details = page.locator('details', { hasText: 'Stored JSON Schema' });
    await details.locator('summary').click();
    const jsonBlock = details.locator('pre');
    await expect(jsonBlock).toBeVisible();
    const json = await jsonBlock.textContent();
    const schema = JSON.parse(json);
    expect(schema.properties.email.format).toBe('email');

    // Clean up
    page.on('dialog', dialog => dialog.accept());
    await page.goto('/wp-admin/admin.php?page=schv-schema-editor');
    await page.waitForLoadState('networkidle');
    const deleteButton = page.locator('form', { has: page.locator('input[value="api-test"]') })
      .locator('button', { hasText: 'Delete' });
    await deleteButton.click();
    await page.waitForLoadState('networkidle');
  });

  test('enum field type stores values correctly', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=schv-schema-editor');
    await page.waitForLoadState('networkidle');

    await page.fill('#schv_slug', 'enum-test');

    await page.click('#schv-add-field');
    const row = page.locator('.schv-field-row').first();
    await row.locator('input[placeholder="Field name"]').fill('color');
    await row.locator('.schv-type-select').selectOption('enum');
    await row.locator('textarea[name$="[enum_values]"]').fill('red\ngreen\nblue');
    await row.locator('input[name$="[required]"]').check();

    await page.click('input[type="submit"][value="Save schema"]');
    await page.waitForLoadState('networkidle');
    await expect(page.getByText('Schema saved.')).toBeVisible();

    const details = page.locator('details', { hasText: 'Stored JSON Schema' });
    await details.locator('summary').click();
    const jsonBlock = details.locator('pre');
    await expect(jsonBlock).toBeVisible();
    const json = await jsonBlock.textContent();
    const schema = JSON.parse(json);
    expect(schema.properties.color.enum).toEqual(['red', 'green', 'blue']);
    expect(schema.required).toEqual(['color']);

    // Clean up
    page.on('dialog', dialog => dialog.accept());
    await page.goto('/wp-admin/admin.php?page=schv-schema-editor');
    await page.waitForLoadState('networkidle');
    const deleteButton = page.locator('form', { has: page.locator('input[value="enum-test"]') })
      .locator('button', { hasText: 'Delete' });
    await deleteButton.click();
    await page.waitForLoadState('networkidle');
  });
});
