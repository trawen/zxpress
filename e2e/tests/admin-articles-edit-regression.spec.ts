import { test, expect } from '@playwright/test';
import { loginViaHyperjump } from '../utils/admin-login';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

test.describe('admin articles create regression', () => {
  test.describe.configure({ timeout: 120_000 });

  test('can create article on admin_articles without 500', async ({ page }) => {
    const pass = process.env.E2E_ADMIN_PASS?.trim();
    test.skip(!pass, 'E2E_ADMIN_PASS not set');

    await loginViaHyperjump(page);

    await page.goto('/admin_articles.php?id=347&issue=2654', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/admin_articles\.php\?id=347&issue=2654/);

    const marker = `[E2E_FIX] ${Date.now()}`;
    await page.locator('textarea[name="new_article_title"]').fill(`Regression ${marker}`);
    await page.locator('textarea[name="new_article_text"]').fill(`Body ${marker}`);
    await page.locator('input[name="article_create"]').evaluate((el: HTMLInputElement) => {
      el.value = '1';
    });

    const saveButton = page.locator('input[type="submit"][name="save"][value="Сохранить"]').first();
    await Promise.all([
      page.waitForURL(/admin_articles\.php\?id=347&issue=2654/, { waitUntil: 'domcontentloaded' }),
      saveButton.click(),
    ]);

    const html = await page.content();
    assertBodyHasNoPhpFatal(html);
    await expect(page.getByText('Произошла ошибка. Попробуйте позже.')).toHaveCount(0);
    const articleTitleCount = await page.locator('textarea[name^="article_title_"]').count();
    expect(articleTitleCount).toBeGreaterThan(0);
  });

  test('can upload screenshot on admin_articles without 500', async ({ page }) => {
    const pass = process.env.E2E_ADMIN_PASS?.trim();
    test.skip(!pass, 'E2E_ADMIN_PASS not set');

    await loginViaHyperjump(page);

    await page.goto('/admin_articles.php?id=347&issue=2654', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/admin_articles\.php\?id=347&issue=2654/);

    const png1x1 = Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQf2N0YAAAAASUVORK5CYII=',
      'base64',
    );
    await page.locator('input[name="upload_screen"]').setInputFiles({
      name: `e2e-${Date.now()}.png`,
      mimeType: 'image/png',
      buffer: png1x1,
    });

    const saveButton = page.locator('input[type="submit"][name="save"][value="Сохранить"]').first();
    await Promise.all([
      page.waitForURL(/admin_articles\.php\?id=347&issue=2654/, { waitUntil: 'domcontentloaded' }),
      saveButton.click(),
    ]);

    const html = await page.content();
    assertBodyHasNoPhpFatal(html);
    await expect(page.getByText('Произошла ошибка. Попробуйте позже.')).toHaveCount(0);
  });

  test('can save issue date with year 2026 without 500', async ({ page }) => {
    const pass = process.env.E2E_ADMIN_PASS?.trim();
    test.skip(!pass, 'E2E_ADMIN_PASS not set');

    await loginViaHyperjump(page);
    await page.goto('/admin_articles.php?id=347&issue=2654', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/admin_articles\.php\?id=347&issue=2654/);

    await page.locator('input[name="issue_date"]').fill('01.01.2026');
    await page.locator('input[name="issue_date_change"]').evaluate((el: HTMLInputElement) => {
      el.value = '1';
    });

    const saveButton = page.locator('input[type="submit"][name="save"][value="Сохранить"]').first();
    await Promise.all([
      page.waitForURL(/admin_articles\.php\?id=347&issue=2654/, { waitUntil: 'domcontentloaded' }),
      saveButton.click(),
    ]);

    const html = await page.content();
    assertBodyHasNoPhpFatal(html);
    await expect(page.getByText('Произошла ошибка. Попробуйте позже.')).toHaveCount(0);
  });
});
