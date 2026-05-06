import type { Download, Page, Response } from '@playwright/test';

/**
 * URLs that send Content-Disposition / binary streams: Chromium starts a download instead of DOM.
 * Wait for the download event; page.goto may reject with "Download is starting".
 */
export async function gotoExpectDownload(page: Page, path: string): Promise<Download> {
  const downloadPromise = page.waitForEvent('download', { timeout: 60_000 });
  await page.goto(path, { timeout: 60_000 }).catch(() => {});
  return downloadPromise;
}

/**
 * /d.php без query: в одних снимках БД возможен 404, в других — отдача zip (attachment → событие download).
 */
export async function gotoDphpBare(page: Page): Promise<{ kind: 'download'; download: Download } | { kind: 'http'; status: number }> {
  const downloadPromise = page.waitForEvent('download', { timeout: 5_000 }).catch(() => null as Download | null);
  let response: Response | null = null;
  try {
    response = await page.goto('/d.php', { waitUntil: 'domcontentloaded', timeout: 60_000 });
  } catch (e: unknown) {
    const msg = String(e);
    if (!msg.includes('Download') && !msg.includes('download')) {
      throw e;
    }
  }
  const download = await downloadPromise;
  if (download) {
    return { kind: 'download', download };
  }
  return { kind: 'http', status: response?.status() ?? 0 };
}

/**
 * Browser navigation with retry for nginx limit_req (503) / rate limits (429).
 * Use instead of APIRequestContext.get — exercises real Chromium navigation.
 */
export async function gotoWithRetry(
  page: Page,
  path: string,
  opts: {
    maxAttempts?: number;
    waitUntil?: 'domcontentloaded' | 'load' | 'commit';
  } = {}
): Promise<Response | null> {
  const maxAttempts = opts.maxAttempts ?? 6;
  const waitUntil = opts.waitUntil ?? 'domcontentloaded';
  let last: Response | null = null;
  for (let attempt = 0; attempt < maxAttempts; attempt++) {
    last = await page.goto(path, { waitUntil, timeout: 60_000 });
    const st = last?.status() ?? 0;
    if (st !== 503 && st !== 429) {
      return last;
    }
    await new Promise((r) => setTimeout(r, 120 * (attempt + 1)));
  }
  return last;
}
