import { Page, TestInfo, test } from '@playwright/test';

export type Role = 'TUTOR' | 'STUDENT1' | 'STUDENT2' | 'NOCREATOR';

export function credentials(role: Role) {
  return {
    login: process.env[`E2E_${role}_LOGIN`] ?? '',
    password: process.env[`E2E_${role}_PASSWORD`] ?? '',
  };
}

export function exerciseRefId(info: TestInfo): string {
  const refId = process.env[`${info.project.name.toUpperCase()}_EXC_REF_ID`] ?? '';
  test.skip(refId === '', `keine Test-Uebung fuer ${info.project.name} in .env`);
  return refId;
}

export async function login(page: Page, role: Role): Promise<void> {
  const { login, password } = credentials(role);
  await page.goto('login.php');
  const passwordField = page.locator('input[type="password"]');
  await page.locator('form:has(input[type="password"]) input[type="text"]').first().fill(login);
  await passwordField.fill(password);
  await passwordField.press('Enter');
  await page.waitForLoadState('networkidle');
}

export async function openExercise(page: Page, refId: string): Promise<void> {
  await page.goto(`goto.php?target=exc_${refId}`);
}
