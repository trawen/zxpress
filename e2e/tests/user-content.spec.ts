import { test, expect } from '@playwright/test';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

test.describe('user-generated content (guestbook)', () => {
  test('invalid captcha shows validation, no PHP fatal', async ({ page }) => {
    await page.goto('/guestbook.php', { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());

    await page.locator('input[name="user_name"]').fill('e2e');
    await page.locator('input[name="user_email"]').fill('e2e@test.local');
    await page.locator('textarea[name="message"]').fill('Playwright smoke: guestbook form path.');
    await page.locator('input[name="confirm_code"]').fill('wrong');
    await page.locator('input[name="submit"]').click();

    const html = await page.content();
    assertBodyHasNoPhpFatal(html);
    await expect(page.locator('body')).toContainText(/Неверный код|неверный/i);
  });

  test('valid captcha + CSRF: message appears after submit (E2E_EXPOSE_CAPTCHA)', async ({ page }) => {
    await page.goto('/guestbook.php', { waitUntil: 'domcontentloaded' });
    const hasMarker = await page.getByTestId('e2e-guestbook-captcha').count();
    test.skip(
      hasMarker === 0,
      'PHP needs E2E_EXPOSE_CAPTCHA=1 (merge docker-compose.test.yml or set env on php service)'
    );
    const marker = page.getByTestId('e2e-guestbook-captcha');
    await expect(marker).toBeVisible({ timeout: 5_000 });
    const code = (await marker.textContent())?.trim() ?? '';
    expect(code.length, 'captcha from data-testid').toBe(6);

    const msg = `Playwright browser E2E ${Date.now()}`;
    await page.locator('input[name="user_name"]').fill('e2e_browser');
    await page.locator('input[name="user_email"]').fill('e2e_browser@test.local');
    await page.locator('textarea[name="message"]').fill(msg);
    await page.locator('input[name="confirm_code"]').fill(code);
    await page.locator('input[name="submit"]').click();

    await expect(page).toHaveURL(/guestbook\.php/);
    const html = await page.content();
    assertBodyHasNoPhpFatal(html);
    await expect(page.locator('body')).toContainText(msg);
    await expect(page.locator('body')).toContainText('e2e_browser');
  });
});
