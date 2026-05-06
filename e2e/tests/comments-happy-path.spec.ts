import { test, expect } from '@playwright/test';
import {
  bookArticlesCommentsPath,
  expectBookArticlesCommentForm,
} from '../utils/book-articles-comments';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

test.describe('book_articles comments happy path', () => {
  test('valid captcha + CSRF: comment text appears (E2E_EXPOSE_CAPTCHA)', async ({ page }) => {
    const path = bookArticlesCommentsPath();
    await page.goto(path, { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());

    const marker = page.getByTestId('e2e-comments-captcha');
    const hasMarker = await marker.count();
    test.skip(
      hasMarker === 0,
      'PHP needs E2E_EXPOSE_CAPTCHA=1 (docker-compose.test.yml php service)'
    );

    await expect(marker).toBeAttached();
    const code = (await marker.textContent())?.trim() ?? '';
    expect(code.length, 'captcha from data-testid e2e-comments-captcha').toBeGreaterThanOrEqual(4);

    const form = await expectBookArticlesCommentForm(page);

    const msg = `Playwright book comment E2E ${Date.now()}`;
    await form.locator('input[name="user_name"]').fill('e2e_book_comment');
    await form.locator('input[name="user_email"]').fill('e2e_book_comment@test.local');
    await form.locator('textarea[name="message"]').fill(msg);
    await form.locator('input[name="confirm_code"]').fill(code);
    await form.locator('input[name="submit"]').click();

    await expect(page).toHaveURL(/book_articles\.php/);
    const html = await page.content();
    assertBodyHasNoPhpFatal(html);
    await expect(page.locator('body')).toContainText(msg);
    await expect(page.locator('body')).toContainText('e2e_book_comment');
  });
});
