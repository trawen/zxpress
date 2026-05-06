/**
 * Regression: bind-mounted ./site (not stale app_code volume), chronology sanity, sidebar titles.
 * Bump site/e2e-deploy-marker.txt when you intentionally change deploy contract expectations.
 */
import * as fs from 'fs';
import * as path from 'path';
import { test, expect } from '@playwright/test';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

const repoRoot = path.resolve(__dirname, '..', '..');
const markerPath = path.join(repoRoot, 'site', 'e2e-deploy-marker.txt');

test.describe('regression: deploy + chronology + sidebar', () => {
  test('served e2e-deploy-marker.txt matches workspace file (bind-mount contract)', async ({ request }) => {
    const expected = fs.readFileSync(markerPath, 'utf8').trim();
    expect(expected.length, 'marker file must be non-empty').toBeGreaterThan(0);

    const res = await request.get('/e2e-deploy-marker.txt');
    expect(res.status(), 'GET e2e-deploy-marker.txt').toBe(200);
    const body = (await res.text()).trim();
    expect(
      body.includes('<') || body.includes('<!DOCTYPE'),
      'got HTML instead of marker — stack is missing site/e2e-deploy-marker.txt (stale volume, old image, or PLAYWRIGHT reuseExistingServer against pre-bind-mount compose). docker compose down && rebuild, or set PLAYWRIGHT_SKIP_WEBSERVER=0 without a stale server on :80'
    ).toBe(false);
    expect(body, 'nginx must serve host ./site (bind-mount), not a stale code snapshot').toBe(expected);
  });

  test('chronology: no PHP fatal, chart has cache-bust, no absurd year heading', async ({ page }) => {
    const res = await page.goto('/chronology.php', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0, 'chronology HTTP').toBeLessThan(500);

    const html = await page.content();
    assertBodyHasNoPhpFatal(html);

    const img = page.locator('img[src*="zxpress_dinamic.png"]');
    await expect(img).toHaveCount(1);
    await expect(img).toHaveAttribute('src', /zxpress_dinamic\.png\?v=\d+/);

    expect(html, 'phantom calendar year heading').not.toContain('2166 год');
    expect(html, 'year heading sanity').not.toMatch(/\b2(1[5-9]\d|[2-9]\d{2})\s*год\b/);
  });

  test('runtime storage aliases: unknown files return nginx 404 (no PHP fallback)', async ({ request }) => {
    const probes: Array<{ route: string; expectedStatus: number }> = [
      { route: '/articles/__e2e_missing__.txt', expectedStatus: 404 },
      { route: '/articles_eng/__e2e_missing__.txt', expectedStatus: 404 },
      { route: '/chapters/__e2e_missing__.txt', expectedStatus: 404 },
      { route: '/pictures/__e2e_missing__.jpg', expectedStatus: 404 },
      { route: '/screens/__e2e_missing__.png', expectedStatus: 404 },
      { route: '/illustrations/__e2e_missing__.png', expectedStatus: 404 },
      { route: '/books_files/__e2e_missing__.zip', expectedStatus: 404 },
      { route: '/files/__e2e_missing__.zip', expectedStatus: 404 },
      { route: '/news_files/__e2e_missing__.jpg', expectedStatus: 404 },
      { route: '/archive/__e2e_missing__.html', expectedStatus: 410 },
      { route: '/cat/__e2e_missing__.html', expectedStatus: 410 },
      { route: '/chapters_images/__e2e_missing__.png', expectedStatus: 404 },
      { route: '/articles_web/__e2e_missing__.html', expectedStatus: 410 },
    ];

    for (const { route, expectedStatus } of probes) {
      const res = await request.get(route);
      expect(res.status(), `${route} status`).toBe(expectedStatus);
      const body = await res.text();
      expect(
        body.includes('Произошла ошибка') || body.includes('ezines') || body.includes('<!DOCTYPE html'),
        `${route} unexpectedly fell back to PHP/app page instead of static alias`
      ).toBe(false);
    }
  });

  test('col-right article links: no raw BBCode in link text (title_plain sidebar)', async ({ page }) => {
    await page.goto('/ezines.php', { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());

    const articleLinks = page.locator('#col-right a[href*="article.php"]');
    const n = await articleLinks.count();
    for (let i = 0; i < n; i++) {
      const text = (await articleLinks.nth(i).textContent())?.trim() ?? '';
      if (!text) {
        continue;
      }
      expect(text, `sidebar article link ${i}`).not.toMatch(/\[(b|i|url|quote)(\]|=)/i);
    }
  });
});
