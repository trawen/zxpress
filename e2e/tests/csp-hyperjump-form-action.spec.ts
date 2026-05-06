import { test, expect } from '@playwright/test';
import { assertHyperjumpCspAllowsLoginPost, getContentSecurityPolicy } from '../utils/csp-hyperjump';

/**
 * Регрессия: без form-action 'self' при default-src 'none' браузер тихо не отправляет
 * форму /hyperjump.php — кажется, что «войти» ничего не делает.
 */
test.describe('CSP / hyperjump login POST', () => {
  test('Content-Security-Policy на /hyperjump.php разрешает POST (form-action self)', async ({
    request,
  }) => {
    const res = await request.get('/hyperjump.php');
    expect(res.ok(), `GET /hyperjump.php ожидался 2xx, получено ${res.status()}`).toBeTruthy();
    const csp = getContentSecurityPolicy(res.headers());
    expect(csp, 'nginx должен отдавать Content-Security-Policy на странице входа').toBeTruthy();
    assertHyperjumpCspAllowsLoginPost(csp);
  });
});
