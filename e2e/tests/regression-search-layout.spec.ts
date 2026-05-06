/**
 * Regression: search results stay two-column (main + sidebar); thumb column stays narrow;
 * sidebar selects fit inside .col-right. Viewport Full HD.
 */
import { test, expect } from '@playwright/test';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

test.describe('search layout (Full HD)', () => {
  test.use({ viewport: { width: 1920, height: 1080 } });

  test('toolbar + thumb column + sidebar stay inside frame', async ({ page }) => {
    const res = await page.goto('/search.php?q=test&s=rw&f=0', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(500);
    assertBodyHasNoPhpFatal(await page.content());

    const left = page.locator('.col-left').first();
    const right = page.locator('#col-right');
    await expect(left).toBeVisible();
    await expect(right).toBeVisible();

    const lb = await left.boundingBox();
    const rb = await right.boundingBox();
    expect(lb, 'col-left box').toBeTruthy();
    expect(rb, 'col-right box').toBeTruthy();
    if (!lb || !rb) {
      return;
    }

    // Сайдбар не должен уезжать под основную колонку: при нормальной сетке правый блок правее и в одной «полосе» по Y
    const wrappedBelow = rb.y > lb.y + lb.height * 0.5 && rb.x < lb.x + 80;
    expect(wrappedBelow, 'col-right must sit beside col-left, not below').toBe(false);
    expect(rb.x, 'sidebar starts to the right of main column').toBeGreaterThan(lb.x + lb.width - 4);

    const thumb = page.locator('td.search-results-thumb').first();
    if ((await thumb.count()) > 0) {
      const tb = await thumb.boundingBox();
      expect(tb, 'thumb cell').toBeTruthy();
      if (tb) {
        expect(tb.width, 'thumb column should stay narrow').toBeLessThanOrEqual(120);
      }
    }

    const toolbar = page.locator('.search-toolbar');
    const content = page.locator('.content').first();
    const cb = await content.boundingBox();
    const tb = await toolbar.boundingBox();
    expect(cb, '.content box').toBeTruthy();
    if (cb && tb) {
      expect(tb.width, 'toolbar should not exceed content width').toBeLessThanOrEqual(cb.width + 2);
    }

    const sideSelect = page.locator('.col-right SELECT.right').first();
    if ((await sideSelect.count()) > 0) {
      const sb = await sideSelect.boundingBox();
      const rbb = await right.boundingBox();
      if (sb && rbb) {
        expect(sb.x + sb.width, 'publication select should stay inside sidebar').toBeLessThanOrEqual(
          rbb.x + rbb.width + 1
        );
      }
    }
  });
});
