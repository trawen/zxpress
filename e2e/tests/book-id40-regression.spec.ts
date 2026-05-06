import { test, expect } from '@playwright/test';
import { gotoWithRetry } from '../utils/page-goto';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

test.describe('book.php regression', () => {
  test('book id=40 opens without php errors', async ({ page }) => {
    const res = await gotoWithRetry(page, '/book.php?id=40');
    const status = res?.status() ?? 0;
    expect(status, 'book.php?id=40 HTTP status').toBeGreaterThanOrEqual(200);
    expect(status, 'book.php?id=40 HTTP status').toBeLessThanOrEqual(399);

    await expect(page).toHaveURL(/book\.php\?id=40/i);
    const html = await page.content();
    assertBodyHasNoPhpFatal(html);
    await expect(page.locator('h1.h1')).toContainText('IS-DOS');
  });
});
