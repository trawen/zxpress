import { test, expect } from '@playwright/test';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';
import { loginViaHyperjump } from '../utils/admin-login';

/**
 * Writes one row into `news` (admin_news.php INSERT path). Gated by E2E_ADMIN_PASS.
 *
 * Credentials: set E2E_ADMIN_USER (e.g. newart) and E2E_ADMIN_PASS via env / CI secrets — never commit.
 */
test.describe('admin news — create content', () => {
  test.describe.configure({ timeout: 90_000 });

  test('hyperjump login → new news → redirect shows title', async ({ page }) => {
    const pass = process.env.E2E_ADMIN_PASS?.trim();
    test.skip(!pass, 'Set E2E_ADMIN_PASS (and E2E_ADMIN_USER if not admin) to run mutation E2E');

    const suffix = `${Date.now()}`;
    const title = `E2E news ${suffix}`;
    const bodyText = `E2E body ${suffix} (safe to delete in admin).`;

    await loginViaHyperjump(page);

    await page.goto('/admin_news.php', { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());

    // Template wraps the form in <div class="admin_news_form"> (not a form class).
    const newsShell = page.locator('div.admin_news_form');
    const newsForm = newsShell.locator('form').first();
    await expect(newsShell).toBeVisible();
    await expect(newsForm.locator('input[name="csrf_token"]')).toHaveCount(1);

    await newsForm.locator('#title').fill(title);
    await newsForm.locator('#text').fill(bodyText);
    await newsForm.locator('input[name="source"]').fill('e2e');
    // date field is pre-filled by server; leave or normalize if empty
    const dateInput = newsForm.locator('input[name="date"]');
    if ((await dateInput.inputValue()).trim() === '') {
      const d = new Date();
      await dateInput.fill(
        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:00`
      );
    }

    await newsForm.locator('input[type="submit"][name="button"][value="Сохранить"]').click();

    await expect(page).toHaveURL(/admin_news\.php\?id=\d+/);
    const html = await page.content();
    assertBodyHasNoPhpFatal(html);
    await expect(page.locator('body')).toContainText(title);
  });
});
