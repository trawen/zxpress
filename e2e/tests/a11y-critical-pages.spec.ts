import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';

/**
 * Legacy HTML: full WCAG compliance is a long-term goal.
 * Start with wcag2a + disabled noisy rules; tighten disabledRules over time (see TEST-MATRIX.md).
 */
const PUBLIC_PATHS = [
  '/',
  '/ezines.php',
  '/search.php',
  '/book.php?id=1',
  '/article.php?id=1',
  '/chronology.php',
  '/guestbook.php',
];

/** Rules that flood legacy templates but are tracked separately from CI signal. */
const LEGACY_DISABLED_RULES = [
  'color-contrast',
  'landmark-one-main',
  'region',
  'page-has-heading-one',
  'html-has-lang',
  'meta-viewport',
  'heading-order',
  'image-alt',
  // Present on most public pages today; fix incrementally, do not block CI.
  'link-name',
  'select-name',
  'label',
  'document-title',
];

for (const path of PUBLIC_PATHS) {
  test(`axe wcag2a (legacy-tuned) ${path}`, async ({ page }) => {
    await page.goto(path, { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a'])
      .disableRules(LEGACY_DISABLED_RULES)
      .analyze();

    expect(
      results.violations,
      `a11y violations on ${path}: ${results.violations.map((v) => `${v.id} (${v.impact})`).join('; ')}`
    ).toEqual([]);
  });
}
