import { test, expect } from '@playwright/test';

test('site responds', async ({ page }) => {
  const res = await page.goto('/ezines.php');
  expect(res?.ok()).toBeTruthy();
});
