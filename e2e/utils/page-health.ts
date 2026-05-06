import { expect, type Page } from '@playwright/test';

/**
 * PHP fatals in HTML — require typical PHP error boilerplate (colon / Uncaught …).
 * Broad "fatal error" matches legitimate page copy (e.g. forum topics).
 */
const FATAL =
  /\b(?:PHP\s+)?(?:Fatal error|Parse error)\s*:|PHP\s+Warning\s*:|PHP\s+Notice\s*:|Uncaught\s+(?:\w+\s+)?(?:Error|Exception|Throwable)\s*:/i;

function htmlWithoutEmbeddedScripts(html: string): string {
  return html
    .replace(/<script\b[\s\S]*?<\/script>/gi, '')
    .replace(/<style\b[\s\S]*?<\/style>/gi, '');
}

export function assertBodyHasNoPhpFatal(html: string): void {
  const body = htmlWithoutEmbeddedScripts(html);
  expect(FATAL.test(body), 'response body must not contain PHP fatal/error markers').toBe(false);
}

export async function assertPageHealthy(page: Page): Promise<void> {
  const html = await page.content();
  assertBodyHasNoPhpFatal(html);
}
