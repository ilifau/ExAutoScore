import { test, expect } from '@playwright/test';

test('Login-Seite ist erreichbar', async ({ page }) => {
  await page.goto('login.php');
  await expect(page.locator('input[type="password"]')).toBeVisible();
  await page.screenshot({ path: `test-results/login-${test.info().project.name}.png` });
});
