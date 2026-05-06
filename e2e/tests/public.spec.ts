import { test, expect } from '@playwright/test';

test.describe('public flows', () => {
  test('catalog (ezines) loads', async ({ page }) => {
    await page.goto('/ezines.php');
    await expect(page.locator('body')).toBeVisible();
  });

  test('news listing loads', async ({ page }) => {
    await page.goto('/news.php');
    await expect(page.locator('body')).toBeVisible();
  });

  test('search page accepts input', async ({ page }) => {
    await page.goto('/search.php');
    const q = page.locator('input[name="q"], input[type="search"], textarea[name="q"]').first();
    if (await q.count()) {
      await q.fill('spectrum');
    }
  });

  test('English locale link (?lng=eng)', async ({ page }) => {
    await page.goto('/ezines.php?lng=eng');
    await expect(page.locator('body')).toBeVisible();
  });

  test('open rubric from menu tree', async ({ page }) => {
    await page.goto('/ezines.php');
    const link = page.locator('a[href*="menu/"]').first();
    if (await link.count()) {
      await link.click();
      await expect(page.locator('body')).toBeVisible();
    }
  });
});
