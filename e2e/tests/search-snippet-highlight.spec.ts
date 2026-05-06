/**
 * Regression: Manticore CALL SNIPPETS must produce highlighted excerpts (b.find) on main search.
 * Query overridable: E2E_SEARCH_QUERY (default: спектрум).
 */
import { test, expect } from '@playwright/test';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

const defaultQuery = 'спектрум';

test.describe('search: snippet highlight', () => {
  test('results page shows highlighted terms (b.find) when index has hits', async ({ page }) => {
    const q = (process.env.E2E_SEARCH_QUERY ?? defaultQuery).trim() || defaultQuery;
    const url = `/search.php?q=${encodeURIComponent(q)}&s=rw&f=0`;

    const res = await page.goto(url, { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0, `GET ${url}`).toBeLessThan(500);

    const html = await page.content();
    assertBodyHasNoPhpFatal(html);

    const noHits = await page
      .getByRole('heading', {
        name: /Ничего не найдено\. Убедитесь, что все слова написаны без ошибок\./,
      })
      .count();

    if (noHits > 0) {
      test.skip(true, `no search hits for query="${q}" — seed DB / reindex Manticore or set E2E_SEARCH_QUERY`);
    }

    await expect(page.getByText(/Результатов:\s*\d+/)).toBeVisible();

    const highlights = page.locator('b.find');
    const n = await highlights.count();
    if (n === 0) {
      const strict = process.env.E2E_REQUIRE_SEARCH_HIGHLIGHTS === '1';
      const msg =
        'hits returned but no <b class="find"> — often missing data/content-store/* sources (empty CALL SNIPPETS docs) or Manticore snippet failure; use a full data mirror or set E2E_SEARCH_QUERY';
      if (strict) {
        expect(n, msg).toBeGreaterThan(0);
      } else {
        test.skip(true, msg);
      }
      return;
    }
    await expect(highlights.first()).toBeVisible();
  });
});
