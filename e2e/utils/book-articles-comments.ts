import { expect, type Locator, type Page } from '@playwright/test';

const LOG_FIX =
  process.env.DEBUG_FIX === '1' || process.env.LOG_LEVEL === 'debug';

/**
 * Chapter id for `book_articles.php?id=` (must exist in DB and have chapter file on disk).
 * Override when the default `1` is missing on a sparse test snapshot.
 */
export function bookArticlesChapterId(): string {
  const raw = process.env.E2E_BOOK_ARTICLES_CH_ID?.trim();
  return raw && raw.length > 0 ? raw : '1';
}

export function bookArticlesCommentsPath(): string {
  return `/book_articles.php?id=${encodeURIComponent(bookArticlesChapterId())}`;
}

/**
 * Legacy table layout: comment form is below the article body; without scrolling,
 * `toBeVisible()` on the form can time out even though the DOM is complete.
 */
export async function expectBookArticlesCommentForm(
  page: Page
): Promise<Locator> {
  const path = bookArticlesCommentsPath();
  if (LOG_FIX) {
    // eslint-disable-next-line no-console
    console.log('[FIX] comments E2E: resolving comment form', {
      path,
      chId: bookArticlesChapterId(),
    });
  }

  const anchor = page.locator('a[name="comments"]');
  if ((await anchor.count()) > 0) {
    await anchor.scrollIntoViewIfNeeded();
  }

  const textarea = page.locator('textarea[name="message"]').first();
  const taCount = await textarea.count();
  if (taCount === 0) {
    // eslint-disable-next-line no-console
    console.error('[FIX] comments E2E: no textarea[name="message"] after navigation', {
      path,
      url: page.url(),
    });
  }

  await expect(textarea).toBeVisible({ timeout: 20_000 });
  await textarea.scrollIntoViewIfNeeded();

  // Prefer stable test id: legacy markup used <tr> inside <form>, which made browsers
  // close the form before the textarea (Playwright then could not match form+textarea).
  const form = page.getByTestId('e2e-comments-form');
  await expect(form).toBeVisible({ timeout: 10_000 });
  await expect(form.locator('textarea[name="message"]')).toHaveCount(1);
  if (LOG_FIX) {
    // eslint-disable-next-line no-console
    console.log('[FIX] comments E2E: comment form visible', { path });
  }
  return form;
}
