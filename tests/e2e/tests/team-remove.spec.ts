import { test, expect, Page } from '@playwright/test';
import { credentials, deleteAssignment, exerciseRefId } from './support/ilias';
import { SAMPLE, Task, asUser, openOverview, openSubmissionForm, setUpSampleTask, submit, waitForSetup } from './support/autoscore';

// StudOn core patch "fau: exAssHook": removing members on the team page calls the team handler of ExAutoScore,
// the removed members get copies of the team files and form single teams (only in the StudOn core patch)
test.describe.configure({ mode: 'serial' });

let task: Task;

async function openTeamPage(page: Page) {
  await openOverview(page, task);
  await page.getByRole('main').getByRole('button', { name: 'Team verwalten' }).first().click();
  await page.waitForLoadState('networkidle');
}

const memberRow = (page: Page, login: string) => page.getByRole('row', { name: new RegExp(login) });

test.beforeAll(async ({ browser }, info) => {
  test.skip(info.project.name !== 'studon11', 'nur im StudOn-Core-Patch');
  test.setTimeout(600_000);
  const refId = exerciseRefId(info);
  const tutor = await asUser(browser, 'TUTOR');
  await deleteAssignment(tutor, refId, 'E2E Team entfernen');
  const title = `E2E Team entfernen ${Date.now()}`;
  task = await setUpSampleTask(tutor, refId, title, true);
  await waitForSetup(tutor, task);
  await tutor.close();

  const s1 = await asUser(browser, 'STUDENT1');
  await openOverview(s1, task);
  await s1.getByRole('main').getByRole('button', { name: 'Team erstellen' }).first().click();
  await openTeamPage(s1);
  await s1.locator('input[name="user_login"]').fill(credentials('STUDENT2').login);
  await s1.getByRole('button', { name: 'Hinzufügen', exact: true }).click();
  await expect(memberRow(s1, credentials('STUDENT2').login)).toBeVisible();
  await s1.close();

  const s2 = await asUser(browser, 'STUDENT2');
  await submit(s2, task, `${SAMPLE}/submissions/correct/aufgabe.py`);
  await s2.close();
});

test('Entfernen zeigt den Hinweis von ExAutoScore und gibt dem entfernten Mitglied ein Einzelteam', async ({ browser }) => {
  const s1 = await asUser(browser, 'STUDENT1');
  await openTeamPage(s1);
  await memberRow(s1, credentials('STUDENT2').login).getByRole('checkbox').check();
  await s1.getByRole('button', { name: 'Entfernen' }).first().click();
  await expect(s1.getByText('Die entfernten Mitglieder erhalten Kopien aller hochgeladenen Dateien des Teams').first()).toBeVisible();
  await s1.getByRole('button', { name: 'Entfernen' }).first().click();
  await expect(s1.getByText('Die Einstellungen wurden gespeichert.').first()).toBeVisible();
  await expect(memberRow(s1, credentials('STUDENT2').login)).toHaveCount(0);

  // the remaining member got a copy of the file of the removed member
  await openSubmissionForm(s1, task);
  await expect(s1.getByRole('main')).toContainText('aufgabe.py');
  await s1.close();

  const s2 = await asUser(browser, 'STUDENT2');
  await openOverview(s2, task);
  const main = s2.getByRole('main');
  await expect(main).not.toContainText('Sie gehören noch keinem Team an');
  await expect(main).toContainText('Team-Mitglieder');
  await expect(main).not.toContainText('Student1, Student1');
  await s2.close();
});

test.afterAll(async ({ browser }, info) => {
  if (info.project.name !== 'studon11') return;
  const page = await asUser(browser, 'TUTOR');
  await deleteAssignment(page, task.refId, 'E2E Team entfernen');
  await page.close();
});
