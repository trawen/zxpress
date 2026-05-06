import { test, expect } from '@playwright/test';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

/**
 * DOM anchors for critical layout (no screenshot baselines — see plan C3).
 */
test.describe('layout anchors', () => {
  test('catalog.php has primary table', async ({ page }) => {
    await page.goto('/catalog.php', { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());
    const table = page.locator('table').first();
    await expect(table, 'catalog should expose a main table').toBeVisible();
  });

  test('ezines.php loads primary content', async ({ page }) => {
    await page.goto('/ezines.php', { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());
    await expect(page.locator('body')).toBeVisible();
    const mainBlock = page.locator('table, .catalog, #content, main').first();
    await expect(mainBlock).toBeVisible();
  });

  test('book.php?id=1 has body and title heading', async ({ page }) => {
    await page.goto('/book.php?id=1', { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());
    await expect(page.locator('body')).toBeVisible();
    const h1 = page.locator('h1').first();
    await expect(h1).toBeVisible();
  });

  test('article.php?id=1 has body and title heading', async ({ page }) => {
    await page.goto('/article.php?id=1', { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());
    await expect(page.locator('body')).toBeVisible();
    const h1 = page.locator('h1').first();
    await expect(h1).toBeVisible();
  });
});
