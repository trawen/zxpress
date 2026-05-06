import { test, expect } from '@playwright/test';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';
import { loginViaHyperjump } from '../utils/admin-login';

/**
 * After successful login, hit every admin/hidden surface that normally returns 403 when anonymous.
 * Requires E2E_ADMIN_PASS (and optional E2E_ADMIN_USER).
 */
const ADMIN_PATHS = [
  '/admin_articles.php',
  '/admin_books.php',
  '/admin_books_light.php',
  '/admin_issue.php',
  '/admin_news.php',
  '/admin_news_upload.php',
  '/gallery_admin.php',
  '/hidden.php',
];

test.describe('admin surfaces (logged in)', () => {
  test.describe.configure({ timeout: 90_000 });

  test.beforeEach(async ({ page }) => {
    const pass = process.env.E2E_ADMIN_PASS?.trim();
    test.skip(!pass, 'E2E_ADMIN_PASS not set');
    await loginViaHyperjump(page);
  });

  for (const path of ADMIN_PATHS) {
    test(`200 HTML without fatals: ${path}`, async ({ page }) => {
      await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(page.url()).toContain(path.split('?')[0]);
      const html = await page.content();
      assertBodyHasNoPhpFatal(html);
    });
  }
});
