import { test, expect } from '@playwright/test';
import { Role, exerciseRefId, login, openExercise } from './support/ilias';

const roles: Role[] = ['TUTOR', 'STUDENT1', 'STUDENT2', 'NOCREATOR'];

for (const role of roles) {
  test(`${role} meldet sich an und oeffnet die Uebung`, async ({ page }, info) => {
    const refId = exerciseRefId(info);
    await login(page, role);
    await expect(page.locator('input[type="password"]')).toHaveCount(0);
    await openExercise(page, refId);
    await expect(page).toHaveURL(new RegExp(`ref_id=${refId}`));
    await page.screenshot({ path: `test-results/uebung-${role}-${info.project.name}.png`, fullPage: true });
  });
}
