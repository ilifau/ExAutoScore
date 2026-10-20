import { test, expect } from '@playwright/test';
import { createAutoScoreAssignment, deleteAssignment, exerciseRefId, openExercise } from './support/ilias';
import { Task, asUser, openGrades } from './support/autoscore';

// StudOn core patch "fau: exTeamSingles": button "Einzelteams bilden" in "Abgaben und Noten"
// creates a team of one for every participant without a team
test.describe.configure({ mode: 'serial' });

let task: Task;

test.beforeAll(async ({ browser }) => {
  const page = await asUser(browser, 'TUTOR');
  const refId = exerciseRefId(test.info());
  await deleteAssignment(page, refId, 'E2E Einzelteams');
  const title = `E2E Einzelteams ${Date.now()}`;
  task = { refId, title, assId: await createAutoScoreAssignment(page, refId, title, true) };
  await page.close();
});

test('Vorher: beide Studentinnen sind keinem Team zugeordnet', async ({ browser }) => {
  const page = await asUser(browser, 'TUTOR');
  await openGrades(page, task);
  for (const name of ['Student1, Student1', 'Student2, Student2']) {
    await expect(page.getByRole('row', { name: new RegExp(name) })).toContainText('Keinem Team zugeordnet');
  }
  await page.close();
});

test('„Einzelteams bilden“ fragt nach und legt für jede ein eigenes Team an', async ({ browser }) => {
  const page = await asUser(browser, 'TUTOR');
  await openGrades(page, task);
  await page.getByRole('button', { name: 'Einzelteams bilden' }).click();
  await expect(page.getByText('Möchten Sie für die folgenden Nutzer Einzelteams bilden?')).toBeVisible();
  for (const name of ['Student1, Student1', 'Student2, Student2']) {
    await expect(page.locator('main')).toContainText(name);
  }
  await page.getByRole('button', { name: 'Erstellen' }).first().click();
  await expect(page.getByText('Die Einzelteams wurden gebildet.').first()).toBeVisible();

  await openGrades(page, task);
  const row1 = page.getByRole('row', { name: /Student1, Student1/ });
  const row2 = page.getByRole('row', { name: /Student2, Student2/ });
  for (const row of [row1, row2]) {
    await expect(row).not.toContainText('Keinem Team zugeordnet');
  }
  await expect(row1, 'jede bildet ein eigenes Team').not.toContainText('Student2, Student2');
  await page.close();
});

test('Studentin sieht sich als einziges Team-Mitglied', async ({ browser }) => {
  const page = await asUser(browser, 'STUDENT1');
  await openExercise(page, task.refId);
  await page.getByRole('link', { name: task.title }).first().click();
  const main = page.locator('main');
  await expect(main).not.toContainText('Sie gehören noch keinem Team an');
  await expect(main).toContainText('Team-Mitglieder');
  await expect(main).not.toContainText('Student2, Student2');
  await page.close();
});

test('Ein zweiter Klick findet niemanden mehr ohne Team', async ({ browser }) => {
  const page = await asUser(browser, 'TUTOR');
  await openGrades(page, task);
  await page.getByRole('button', { name: 'Einzelteams bilden' }).click();
  await expect(page.getByText('Es wurden keine Teilnehmer ohne Team gefunden.').first()).toBeVisible();
  await page.close();
});

test.afterAll(async ({ browser }) => {
  const page = await asUser(browser, 'TUTOR');
  await deleteAssignment(page, task.refId, 'E2E Einzelteams');
  await page.close();
});
