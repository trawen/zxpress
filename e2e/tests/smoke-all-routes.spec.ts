import { test, expect } from '@playwright/test';
import {
  publicHtmlRoutes,
  publicNonHtmlRoutes,
  forbiddenAnonymousRoutes,
  staticAssetRoutes,
  type RouteExpectation,
} from '../fixtures/site-routes';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';
import { gotoDphpBare, gotoExpectDownload, gotoWithRetry } from '../utils/page-goto';

function runRouteCase(routes: RouteExpectation[], checkFatal: boolean): void {
  for (const r of routes) {
    test(`GET ${r.path}`, async ({ page }) => {
      if (r.expectDownload) {
        const download = await gotoExpectDownload(page, r.path);
        expect(download.suggestedFilename() || download.url()).toBeTruthy();
        return;
      }
      const res = await gotoWithRetry(page, r.path);
      const st = res?.status() ?? 0;
      const min = r.statusMin ?? 200;
      const max = r.statusMax ?? 399;
      expect(st, `HTTP ${r.path}`).toBeGreaterThanOrEqual(min);
      expect(st, `HTTP ${r.path}`).toBeLessThanOrEqual(max);
      const ct = (res?.headers()['content-type'] || '').toLowerCase();
      if (checkFatal && !r.textOnly && st >= 200 && st < 300 && ct.includes('text/html')) {
        assertBodyHasNoPhpFatal(await page.content());
      }
    });
  }
}

test.describe('manifest: public HTML', () => {
  runRouteCase(publicHtmlRoutes, true);
});

test.describe('manifest: RSS / JSON / XML', () => {
  runRouteCase(publicNonHtmlRoutes, false);
});

test.describe('manifest: must be 403 anonymous', () => {
  runRouteCase(forbiddenAnonymousRoutes, false);
});

/** Real browser follows redirects — assert final URL (chapter → book_articles). */
test.describe('manifest: redirects (browser)', () => {
  test('GET /chapter.php?id=1 redirects away from chapter.php', async ({ page }) => {
    await gotoWithRetry(page, '/chapter.php?id=1');
    await expect(page).not.toHaveURL(/chapter\.php/);
    await expect(page).toHaveURL(/book_articles/i);
  });
});

test.describe('manifest: d.php (browser — download или HTTP)', () => {
  test('GET /d.php без параметров — либо attachment, либо ожидаемый HTTP-ответ', async ({ page }) => {
    const out = await gotoDphpBare(page);
    if (out.kind === 'download') {
      expect(out.download.suggestedFilename() || out.download.url()).toBeTruthy();
    } else {
      expect(out.status).toBeGreaterThanOrEqual(400);
      expect(out.status).toBeLessThan(600);
    }
  });
});

test.describe('manifest: static assets', () => {
  runRouteCase(staticAssetRoutes, false);
});
