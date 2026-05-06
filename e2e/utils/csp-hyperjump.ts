/**
 * Nginx sends CSP on HTML responses. With default-src 'none', browsers block
 * POST from /hyperjump.php unless form-action allows the target (e.g. 'self').
 * @see conf/nginx-site.conf
 */

const LOG_FIX = process.env.DEBUG_FIX === '1' || process.env.LOG_LEVEL === 'debug';

export function getContentSecurityPolicy(headers: Record<string, string>): string | undefined {
  const raw =
    headers['content-security-policy'] ??
    headers['Content-Security-Policy'] ??
    Object.entries(headers).find(([k]) => k.toLowerCase() === 'content-security-policy')?.[1];
  const v = raw?.trim();
  return v || undefined;
}

/**
 * Throws with an operator-facing message if login POST would be blocked by CSP.
 */
export function assertHyperjumpCspAllowsLoginPost(csp: string | undefined): void {
  if (LOG_FIX) {
    console.log('[FIX] hyperjump CSP check', { snippet: csp?.slice(0, 120) });
  }
  if (!csp?.trim()) {
    throw new Error(
      '[FIX] /hyperjump.php: отсутствует заголовок Content-Security-Policy — ожидается CSP от nginx.',
    );
  }
  const policy = csp.trim();
  // Our production policy uses default-src 'none'; then form-action must exist.
  const defaultSrcNone = /default-src\s+'none'/i.test(policy);
  if (defaultSrcNone) {
    const hasFormActionSelf =
      /form-action[^;]*'self'/i.test(policy) || /form-action[^;]*"self"/i.test(policy);
    if (!hasFormActionSelf) {
      const hint =
        "В репозитории в conf/nginx-site.conf должна быть директива form-action 'self' рядом с default-src 'none'. " +
        'После правки конфига на сервере или в Docker bind-mount выполни nginx -s reload (иначе nginx отдаёт старый CSP из памяти). ' +
        'Проверка без прокси: env -u …proxy… curl -sSI \"https://<хост>/hyperjump.php\" | grep -i content-security';
      throw new Error(
        `[FIX] CSP блокирует POST логина: при default-src 'none' нет form-action 'self'. ${hint}\n` +
          `Текущий CSP (начало): ${policy.slice(0, 280)}${policy.length > 280 ? '…' : ''}`,
      );
    }
    if (LOG_FIX) console.log('[FIX] hyperjump CSP: form-action self OK');
  } else if (LOG_FIX) {
    console.log('[FIX] hyperjump CSP: default-src is not \'none\', skipping default-src/form-action gate');
  }

  // If `form-action` is explicitly set without 'self', same-origin POST can still be blocked.
  if (!/default-src\s+'none'/i.test(policy)) {
    const hasFormActionDirective = /\bform-action\s+/i.test(policy);
    if (hasFormActionDirective && !/form-action[^;]*'self'/i.test(policy) && !/form-action[^;]*"self"/i.test(policy)) {
      throw new Error(
        `[FIX] CSP: директива form-action задана без 'self' — POST логина на тот же origin может быть заблокирован.\n` +
          `Текущий CSP (начало): ${policy.slice(0, 280)}${policy.length > 280 ? '…' : ''}`,
      );
    }
  }
}
