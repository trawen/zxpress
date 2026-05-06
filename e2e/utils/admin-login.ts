import type { Page } from '@playwright/test';

/**
 * Log in via hyperjump.php. Requires E2E_ADMIN_PASS (and optional E2E_ADMIN_USER).
 */
export function requireAdminEnv(): { user: string; pass: string } {
  const pass = process.env.E2E_ADMIN_PASS?.trim();
  if (!pass) {
    throw new Error('E2E_ADMIN_PASS is not set');
  }
  const user = process.env.E2E_ADMIN_USER?.trim() || 'admin';
  return { user, pass };
}

export async function loginViaHyperjump(page: Page): Promise<void> {
  const { user, pass } = requireAdminEnv();
  await page.goto('/hyperjump.php', { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="username"]').fill(user);
  await page.locator('input[name="password"]').fill(pass);
  await Promise.all([
    page.waitForURL(/admin_articles\.php/i, {
      timeout: 60_000,
      waitUntil: 'domcontentloaded',
    }),
    page.locator('button[name="auth_submit"]').click(),
  ]);
}

/** Marker + fake year for bulk cleanup (titles + book date). */
export const E2E_ADMIN_MARKER = 'E2E_ZXPRESS';
export const E2E_ADMIN_YEAR_TAG = 'y3000';
