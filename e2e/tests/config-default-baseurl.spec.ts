import { test, expect } from '@playwright/test';
import { CANONICAL_E2E_BASE_URL } from '../constants';

test.describe('playwright config defaults', () => {
  test('defaults baseURL to canonical staging when BASE_URL is unset', async ({}, testInfo) => {
    test.skip(
      Boolean(process.env.BASE_URL?.trim()),
      'Requires BASE_URL unset to assert playwright.config.ts default (CI and run-all-tests.sh set BASE_URL explicitly)'
    );
    expect(testInfo.project.use.baseURL).toBe(CANONICAL_E2E_BASE_URL);
  });
});
