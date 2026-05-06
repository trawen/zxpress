import { test, expect } from '@playwright/test';
import { gotoWithRetry } from '../utils/page-goto';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

test.describe('article.php entity regression', () => {
  test('article id=13772 renders clean h1 title', async ({ page }) => {
    const res = await gotoWithRetry(page, '/article.php?id=13772');
    const status = res?.status() ?? 0;
    expect(status, 'article.php?id=13772 HTTP status').toBeGreaterThanOrEqual(200);
    expect(status, 'article.php?id=13772 HTTP status').toBeLessThanOrEqual(399);

    const html = await page.content();
    assertBodyHasNoPhpFatal(html);

    const h1 = page.locator('h1').first();
    await expect(h1).toBeVisible();
    await expect(h1).toContainText('АLEХ');
    await expect(h1).not.toContainText('&amp;#');
    await expect(h1).not.toContainText('&#039;');
  });

  test('other articles list does not contain escaped numeric entities', async ({ page }) => {
    await gotoWithRetry(page, '/article.php?id=13772');
    const section = page.locator('table h2');
    await expect(section.first()).toBeVisible();
    await expect(section.first()).not.toContainText('&amp;#');
    await expect(section.first()).not.toContainText('&#039;');
  });
});
