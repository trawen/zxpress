import { test, expect } from '@playwright/test';
import {
  bookArticlesCommentsPath,
  expectBookArticlesCommentForm,
} from '../utils/book-articles-comments';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

test.describe('comments form flows', () => {
  test('book_articles comment form rejects invalid captcha without PHP fatal', async ({ page }) => {
    await page.goto(bookArticlesCommentsPath(), { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());

    const form = await expectBookArticlesCommentForm(page);
    await expect(form.locator('input[name="csrf_token"]')).toHaveCount(1);

    await form.locator('input[name="user_name"]').fill('e2e_commenter');
    await form.locator('input[name="user_email"]').fill('e2e_commenter@test.local');
    await form.locator('textarea[name="message"]').fill('E2E comment form validation path.');
    await form.locator('input[name="confirm_code"]').fill('wrong');
    await form.locator('input[name="submit"]').click();

    const html = await page.content();
    assertBodyHasNoPhpFatal(html);
    await expect(page.locator('body')).toContainText(/Неверный код|неверный/i);
  });
});
