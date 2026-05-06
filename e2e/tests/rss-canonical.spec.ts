import { test, expect } from '@playwright/test';

/**
 * RSS Atom links must use the same origin as BASE_URL (no hardcoded zxpress.ru on staging).
 */
test('GET /rss.php — atom link href matches BASE_URL origin', async ({ request, baseURL }) => {
	expect(baseURL, 'Playwright baseURL').toBeTruthy();
	const res = await request.get('/rss.php');
	expect(res.ok(), `HTTP ${res.status()}`).toBeTruthy();
	const body = await res.text();
	const m = body.match(/<link[^>]+href="([^"]+)"/i);
	expect(m, 'expected at least one <link href="..."> in Atom feed').toBeTruthy();
	const href = m![1];
	const feedOrigin = new URL(href).origin;
	const expectedOrigin = new URL(baseURL!).origin;
	expect(feedOrigin).toBe(expectedOrigin);
});
