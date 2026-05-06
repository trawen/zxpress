import { test, expect } from '@playwright/test';
import { assertHyperjumpCspAllowsLoginPost, getContentSecurityPolicy } from '../utils/csp-hyperjump';

test.describe('admin login form', () => {
  test.describe.configure({ timeout: 90_000 });

  test('hyperjump login page renders', async ({ page }) => {
    const response = await page.goto('/hyperjump.php');
    const csp = getContentSecurityPolicy(response?.headers() ?? {});
    assertHyperjumpCspAllowsLoginPost(csp);
    await expect(page.getByRole('heading', { name: /Вход в кладовые/i })).toBeVisible();
  });

  test('submit with env password when set', async ({ page }) => {
    const pass = process.env.E2E_ADMIN_PASS?.trim();
    test.skip(!pass, 'E2E_ADMIN_PASS not set');
    const user = process.env.E2E_ADMIN_USER?.trim() || 'admin';
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        console.log('[FIX] hyperjump login console error:', msg.text());
      }
    });
    const postToHyperjump: string[] = [];
    page.on('request', (req) => {
      if (req.method() === 'POST' && /hyperjump\.php/i.test(req.url())) {
        postToHyperjump.push(req.url());
      }
    });
    const response = await page.goto('/hyperjump.php');
    const csp = getContentSecurityPolicy(response?.headers() ?? {});
    assertHyperjumpCspAllowsLoginPost(csp);
    await page.locator('input[name="username"]').fill(user);
    await page.locator('input[name="password"]').fill(pass!);
    const clickLogin = page.locator('button[name="auth_submit"]');
    await clickLogin.scrollIntoViewIfNeeded();
    await Promise.all([
      Promise.race([
        page.waitForURL(/admin_articles\.php/i, {
          timeout: 60_000,
          waitUntil: 'domcontentloaded',
        }),
        page
          .getByText(/Неверный логин или пароль/i)
          .waitFor({ state: 'visible', timeout: 60_000 }),
        page
          .getByText(/CSRF token mismatch/i)
          .waitFor({ state: 'visible', timeout: 60_000 }),
        page
          .getByText(/нет прав администратора/i)
          .waitFor({ state: 'visible', timeout: 60_000 }),
      ]),
      clickLogin.click(),
    ]);
    if (page.url().includes('hyperjump.php')) {
      const csrf = await page.getByText(/CSRF token mismatch/i).isVisible().catch(() => false);
      if (csrf) {
        throw new Error('[FIX] CSRF отклонён — сессия/cookie между GET и POST (проверьте User-Agent: is_bot() в init.inc не должен резать Chromium).');
      }
      const noRights = await page.getByText(/нет прав администратора/i).isVisible().catch(() => false);
      if (noRights) {
        throw new Error(
          '[FIX] Пароль верный, но users.level не 1 и не NULL — выполните UPDATE users SET level=1 WHERE username=…',
        );
      }
      const bad = await page.getByText(/Неверный логин или пароль/i).isVisible().catch(() => false);
      if (bad) {
        throw new Error('[FIX] Отказ входа: неверный логин или пароль (пользователь не найден или hash не совпал).');
      }
      throw new Error('[FIX] Остались на /hyperjump.php без сообщения об ошибке — проверьте ответ POST в Network.');
    }
    expect(
      postToHyperjump.length,
      '[FIX] браузер не отправил POST на hyperjump.php — возможна блокировка CSP (см. assertHyperjumpCspAllowsLoginPost) или форма не submit',
    ).toBeGreaterThan(0);
    await expect(page.locator('body')).toBeVisible();
  });
});
