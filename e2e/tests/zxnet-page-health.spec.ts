/**
 * ZXNet: page renders without PHP fatal; topic bodies must not show double-encoded entities.
 * Fresh DBs load db/init/90-e2e-zxnet-encoding-fixture.sql (URL /zxnet/e2e.talk/911001).
 * Existing volumes without that fixture fall back to /zxnet/462.talk/1 when data exists.
 */
import { test, expect } from '@playwright/test';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

const TOPIC_CANDIDATES = ['/zxnet/e2e.talk/911001', '/zxnet/462.talk/1'] as const;

test.describe('zxnet', () => {
  test('zxnet index: HTTP ok, no PHP fatal', async ({ page }) => {
    const res = await page.goto('/zxnet.php', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0, 'zxnet HTTP').toBeLessThan(500);
    assertBodyHasNoPhpFatal(await page.content());
  });

  test('zxnet topic: no visible &quot; / &#34; in message bodies', async ({ page }) => {
    let checked = false;

    for (const path of TOPIC_CANDIDATES) {
      const res = await page.goto(path, { waitUntil: 'domcontentloaded' });
      if ((res?.status() ?? 0) >= 500) {
        continue;
      }
      const html = await page.content();
      assertBodyHasNoPhpFatal(html);

      const blocks = page.locator('div[style*="620px"]');
      const n = await blocks.count();
      if (n === 0) {
        continue;
      }

      for (let i = 0; i < n; i++) {
        const t = ((await blocks.nth(i).innerText()) ?? '').trim();
        if (t.length < 2) {
          continue;
        }
        expect(t, `topic body ${path} #${i}`).not.toContain('&quot;');
        expect(t, `topic body ${path} #${i}`).not.toContain('&#34;');
      }

      if (path === '/zxnet/e2e.talk/911001') {
        let sawFixture = false;
        for (let i = 0; i < n; i++) {
          const t = ((await blocks.nth(i).innerText()) ?? '').trim();
          if (t.includes('Quote test')) {
            expect(t).toMatch(/"ZX Spectrum"/);
            sawFixture = true;
            break;
          }
        }
        expect(sawFixture, 'e2e.talk fixture message with decoded quotes').toBe(true);
      }

      checked = true;
      break;
    }

    test.skip(
      !checked,
      'no zxnet topic rows: recreate db volume or run db/init/90-e2e-zxnet-encoding-fixture.sql'
    );
  });
});
