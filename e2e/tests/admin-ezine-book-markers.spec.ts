import { test, expect } from '@playwright/test';
import { assertBodyHasNoPhpFatal } from '../utils/page-health';
import {
  E2E_ADMIN_MARKER,
  E2E_ADMIN_YEAR_TAG,
  loginViaHyperjump,
} from '../utils/admin-login';

/**
 * Creates one electronic journal (press, type «Журнал») and one book with a shared marker
 * and book release date 01.01.2037 — easy to find and delete (see e2e/README.md).
 * (Use a year ≤2038 so `books.date` Unix timestamp fits a signed 32-bit INT.)
 */
test.describe('admin — E2E marker press + book (cleanup-friendly)', () => {
  test.describe.configure({ timeout: 90_000 });

  test.beforeEach(async ({ page }) => {
    const pass = process.env.E2E_ADMIN_PASS?.trim();
    test.skip(!pass, 'E2E_ADMIN_PASS not set');
  });

  test('create press (journal) with marker + first issue', async ({ page }) => {
    const ts = Date.now();
    // press.title is varchar(32); issue.title is varchar(16)
    const title = `${E2E_ADMIN_MARKER} ${E2E_ADMIN_YEAR_TAG} ${String(ts).slice(-7)}`.slice(
      0,
      32
    );
    const issueTitle = `${E2E_ADMIN_YEAR_TAG}${String(ts).slice(-8)}`.slice(0, 16);

    await loginViaHyperjump(page);

    await page.goto('/admin_issue.php?id=0', { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());

    // Logout form also has csrf_token — scope to the press/issue editor form.
    const pressForm = page.locator('form:has(#press_change)');
    await pressForm.locator('input[name="csrf_token"]').waitFor({ state: 'attached' });
    await pressForm.locator('input[name="title"]').fill(title);
    await pressForm.locator('select[name="type"]').selectOption('1');
    await pressForm.locator('select[name="city"]').selectOption({ index: 0 });
    await pressForm.locator('input[name="numbers"]').fill('1');
    await pressForm.locator('input[name="add_issue"]').fill(issueTitle);

    await pressForm.locator('#press_change').evaluate((el: HTMLInputElement) => {
      el.value = '1';
    });

    await pressForm.locator('input[type="submit"][name="save"][value="save"]').click();

    await expect(page).toHaveURL(/admin_issue\.php\?id=\d+/);
    assertBodyHasNoPhpFatal(await page.content());
    await expect(page.locator('body')).toContainText(title);
  });

  test('create book with marker and date 01.01.2037', async ({ page }) => {
    const ts = Date.now();
    const title1 = `${E2E_ADMIN_MARKER} book ${E2E_ADMIN_YEAR_TAG} ${ts}`;

    await loginViaHyperjump(page);

    await page.goto('/admin_books.php?id=0', { waitUntil: 'domcontentloaded' });
    assertBodyHasNoPhpFatal(await page.content());

    const bookForm = page.locator('form:has(#book_change)');
    await bookForm.locator('input[name="csrf_token"]').waitFor({ state: 'attached' });

    await bookForm.locator('input[name="title1"]').fill(title1);
    await bookForm.locator('input[name="title2"]').fill('E2E characteristic');
    await bookForm.locator('textarea[name="authors"]').fill('e2e');
    await bookForm.locator('textarea[name="annotation"]').fill('E2E cleanup marker — safe to delete.');
    await bookForm.locator('select[name="type"]').selectOption('1');
    await bookForm.locator('select[name="city"]').selectOption('0');
    await bookForm.locator('select[name="language"]').selectOption({ index: 0 });
    await bookForm.locator('input[name="isbn"]').fill('');
    await bookForm.locator('input[name="pages"]').fill('1');
    await bookForm.locator('input[name="circulation"]').fill('1');
    await bookForm.locator('input[name="date"]').fill('01.01.2037');

    await bookForm.locator('#book_change').evaluate((el: HTMLInputElement) => {
      el.value = '1';
    });

    // Two "Сохранить" submits in the same form (book body vs tag row) — use the last primary save.
    await bookForm.locator('input[type="submit"][name="save"][value="Сохранить"]').last().click();

    await expect(page).toHaveURL(/admin_books\.php\?id=\d+/);
    assertBodyHasNoPhpFatal(await page.content());
    await expect(page.locator('input[name="title1"]')).toHaveValue(title1);
  });
});
