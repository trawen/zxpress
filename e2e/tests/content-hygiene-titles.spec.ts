import { test, expect } from '@playwright/test';

test.describe('content hygiene — visible titles', () => {
  test('catalog table text has no raw HTML tag junk', async ({ page }) => {
    await page.goto('/catalog.php');
    const table = page.locator('table').first();
    await expect(table).toBeVisible();
    const text = await table.innerText();
    expect(text).not.toMatch(/<\/span>|<div[\s>]|<span[\s>]/i);
  });

  test('search results area avoids visible markup fragments', async ({ page }) => {
    await page.goto('/search.php?q=spectrum');
    const body = await page.locator('body').innerText();
    expect(body).not.toMatch(/<\/span>/i);
  });
});
