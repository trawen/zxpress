import { test, expect } from '@playwright/test';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

/**
 * Discovers same-origin pages reachable from / and /ezines.php (BFS, real DOM anchors).
 * Skips routes that are known broken in some DB snapshots (tag.php, rubrics_.php).
 *
 * Env:
 *   E2E_MAX_CRAWL_PAGES (default 600)
 *   E2E_MAX_CRAWL_DEPTH (default 4)
 */
const MAX_PAGES = Math.min(parseInt(process.env.E2E_MAX_CRAWL_PAGES || '600', 10), 5000);
const MAX_DEPTH = Math.min(parseInt(process.env.E2E_MAX_CRAWL_DEPTH || '4', 10), 12);

const SKIP_EXT =
  /\.(jpg|jpeg|png|gif|webp|ico|css|js|mjs|woff2?|ttf|eot|svg|zip|rar|7z|pdf|mp3|mp4|wasm|map)$/i;

function skipBrokenPath(path: string): boolean {
  if (path.includes('tag.php')) {
    return true;
  }
  if (path.includes('rubrics_.php')) {
    return true;
  }
  return false;
}

test.describe('crawl', () => {
  test.setTimeout(600_000);

  test('same-origin BFS from / and /ezines.php — no 5xx, no PHP fatals in HTML', async ({
    page,
    baseURL,
  }) => {
    expect(baseURL).toBeTruthy();
    const origin = new URL(baseURL!).origin;

    const seeds = ['/', '/ru', '/ezines.php', '/ru/ezines'];
    const visited = new Set<string>();
    const queue: { path: string; depth: number }[] = seeds.map((p) => ({ path: p, depth: 0 }));

    while (queue.length > 0 && visited.size < MAX_PAGES) {
      const item = queue.shift()!;
      const { path, depth } = item;
      if (depth > MAX_DEPTH) {
        continue;
      }
      if (visited.has(path)) {
        continue;
      }
      visited.add(path);

      const response = await page.goto(path, { waitUntil: 'domcontentloaded', timeout: 60_000 });
      const st = response?.status() ?? 0;
      expect.soft(st, `GET ${path}`).toBeGreaterThanOrEqual(200);
      expect.soft(st, `GET ${path}`).toBeLessThan(500);

      const ct = (response?.headers()['content-type'] || '').toLowerCase();
      if (st >= 200 && st < 300 && ct.includes('text/html')) {
        const html = await page.content();
        assertBodyHasNoPhpFatal(html);
      }

      // Stay under nginx limit_req on *.php (e.g. 5r/s + burst); short gaps caused 503 during BFS.
      await new Promise((r) => setTimeout(r, 220));

      if (depth >= MAX_DEPTH) {
        continue;
      }

      const hrefs = await page.$$eval('a[href]', (as) =>
        as.map((a) => (a as HTMLAnchorElement).getAttribute('href')).filter((h): h is string => !!h)
      );

      for (const href of hrefs) {
        const t = href.trim();
        if (!t || t.startsWith('mailto:') || t.startsWith('javascript:') || t.startsWith('#')) {
          continue;
        }
        let abs: URL;
        try {
          abs = new URL(t, origin + path);
        } catch {
          continue;
        }
        if (abs.origin !== origin) {
          continue;
        }
        if (SKIP_EXT.test(abs.pathname)) {
          continue;
        }
        const next = abs.pathname + (abs.search || '');
        if (skipBrokenPath(next)) {
          continue;
        }
        if (visited.has(next) || queue.some((q) => q.path === next)) {
          continue;
        }
        queue.push({ path: next, depth: depth + 1 });
      }
    }
  });
});
