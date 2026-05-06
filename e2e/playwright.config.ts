import { defineConfig } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { CANONICAL_E2E_BASE_URL } from './constants';

/**
 * Load repo-root `.env.e2e` then `e2e/.env.e2e` (gitignored) so BASE_URL / E2E_* work without `export`.
 * Later file overrides earlier for the same key. Real env vars already set in the shell always win.
 */
function parseEnvE2eContent(text: string): Record<string, string> {
  const out: Record<string, string> = {};
  const normalized = text.replace(/^\uFEFF/, '');
  for (const line of normalized.split('\n')) {
    const t = line.trim();
    if (!t || t.startsWith('#')) {
      continue;
    }
    const eq = t.indexOf('=');
    if (eq === -1) {
      continue;
    }
    const key = t.slice(0, eq).trim();
    if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(key)) {
      continue;
    }
    let val = t.slice(eq + 1).trim();
    if (
      (val.startsWith('"') && val.endsWith('"')) ||
      (val.startsWith("'") && val.endsWith("'"))
    ) {
      val = val.slice(1, -1);
    }
    out[key] = val;
  }
  return out;
}

function mergeEnvRecords(
  into: Record<string, string>,
  parsed: Record<string, string>,
  onlyKeys?: (k: string) => boolean,
): void {
  for (const [key, val] of Object.entries(parsed)) {
    if (onlyKeys && !onlyKeys(key)) {
      continue;
    }
    // Empty value in a later file must not wipe a password from an earlier file.
    if (val.trim() === '') {
      continue;
    }
    into[key] = val;
  }
}

function isEnvUnset(key: string): boolean {
  const v = process.env[key];
  return v === undefined || v === '';
}

/** Keys in repo-root `.env` that apply to Playwright (rest of `.env` is for PHP/MySQL only). */
function isPlaywrightKeyFromRootEnv(key: string): boolean {
  return (
    key.startsWith('E2E_') ||
    key === 'BASE_URL' ||
    key === 'PLAYWRIGHT_SKIP_WEBSERVER'
  );
}

function loadEnvE2eFile(): void {
  const rootDotEnv = path.join(__dirname, '..', '.env');
  const rootE2e = path.join(__dirname, '..', '.env.e2e');
  const localE2e = path.join(__dirname, '.env.e2e');
  const merged: Record<string, string> = {};
  if (fs.existsSync(rootDotEnv)) {
    mergeEnvRecords(
      merged,
      parseEnvE2eContent(fs.readFileSync(rootDotEnv, 'utf8')),
      isPlaywrightKeyFromRootEnv,
    );
  }
  if (fs.existsSync(rootE2e)) {
    mergeEnvRecords(merged, parseEnvE2eContent(fs.readFileSync(rootE2e, 'utf8')));
  }
  if (fs.existsSync(localE2e)) {
    mergeEnvRecords(merged, parseEnvE2eContent(fs.readFileSync(localE2e, 'utf8')));
  }
  for (const [key, val] of Object.entries(merged)) {
    if (!isEnvUnset(key)) {
      continue;
    }
    process.env[key] = val;
  }
}

loadEnvE2eFile();

const rawBase = process.env.BASE_URL?.trim();
const baseURL = rawBase || CANONICAL_E2E_BASE_URL;

const repoRoot = path.join(__dirname, '..');
const skipWebServer = process.env.PLAYWRIGHT_SKIP_WEBSERVER === '1';

function isLocalPlaywrightTarget(url: string): boolean {
  try {
    const u = new URL(url);
    return u.hostname === '127.0.0.1' || u.hostname === 'localhost' || u.hostname === '[::1]';
  } catch {
    return false;
  }
}

/** Auto-start Docker only when testing a local stack (never for canonical HTTPS staging). */
const shouldStartWebServer = !skipWebServer && isLocalPlaywrightTarget(baseURL);

export default defineConfig({
  testDir: './tests',
  // Sequential HTTP checks avoid nginx limit_req 503 when many routes are probed at once.
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    trace: 'on-first-retry',
    video: 'off',
    // Do not use HTTP(S)_PROXY from the host (corporate proxy breaks direct hits to 127.0.0.1 / staging).
    // Complements e2e/package.json `env -u …` when someone runs `npx playwright test` by habit.
    launchOptions: {
      args: ['--no-proxy-server'],
    },
  },
  ...(shouldStartWebServer
    ? {
        webServer: {
          command:
            'docker compose -f docker-compose.yml -f docker-compose.test.yml up --build --wait',
          cwd: repoRoot,
          url: 'http://127.0.0.1:80',
          reuseExistingServer: true,
          timeout: 240000,
        },
      }
    : {}),
});
