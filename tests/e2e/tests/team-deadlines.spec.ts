import { test, expect } from '@playwright/test';
import { deleteAssignment, exerciseRefId } from './support/ilias';
import {
  SAMPLE, Task, asUser, createTeam, openGrades, openOverview, setDeadline, setIndividualDeadline, setUpSampleTask, submit,
  waitForSetup, waitForText,
} from './support/autoscore';

// Team assignment: the individual deadline of a team must hold back the result of all members.
// The ILIAS 10/11 core resolves teams only for upload teams, the plugin reads the team deadline itself.
test.describe.configure({ mode: 'serial' });

let task: Task;

test.beforeAll(async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  const refId = exerciseRefId(test.info());
  for (const leftover of ['E2E Team-Frist', 'E2E Team-Erkundung']) {
    await deleteAssignment(page, refId, leftover);
  }
  task = await setUpSampleTask(page, refId, `E2E Team-Frist ${Date.now()}`, true);
  await waitForSetup(page, task);
  await setDeadline(page, task, 60);
  await createTeam(page, task, [/e2e_student1|Student1, Student1/, /e2e_student2|Student2, Student2/]);
  await page.close();
});

test('Tutor sieht ein Team mit beiden Studentinnen', async ({ browser }) => {
  const page = await asUser(browser, 'TUTOR');
  await openGrades(page, task);
  const row = page.getByRole('row', { name: /Student1, Student1/ });
  await expect(row).toContainText('Student2, Student2');
  await page.close();
});

test('Studentin 1 gibt für das Team ab', async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'STUDENT1');
  await submit(page, task, `${SAMPLE}/submissions/correct/aufgabe.py`);
  await waitForText(page, () => openOverview(page, task), /Tests bestanden/);
  await page.close();
});

test('Verlängerte Team-Frist hält die Bewertung für beide Mitglieder zurück', async ({ browser }) => {
  const tutor = await asUser(browser, 'TUTOR');
  await setIndividualDeadline(tutor, task, /Student1, Student1/, 24 * 60);
  await setDeadline(tutor, task, -60);
  await tutor.close();

  for (const role of ['STUDENT1', 'STUDENT2'] as const) {
    const page = await asUser(browser, role);
    await openOverview(page, task);
    const main = page.locator('main');
    await expect(main, `${role} sieht die sofortige Rückmeldung`).toContainText('Sofortige Rückmeldung');
    await expect(main, `${role} darf vor Ende der Team-Frist keine Punkte sehen`).not.toContainText('Erreichte Punkte');
    await page.close();
  }
});

test('Gegenprobe: nach Ende der Team-Frist sehen beide die Bewertung', async ({ browser }) => {
  const tutor = await asUser(browser, 'TUTOR');
  await setDeadline(tutor, task, -180);
  await setIndividualDeadline(tutor, task, /Student1, Student1/, -60);
  await tutor.close();

  for (const role of ['STUDENT1', 'STUDENT2'] as const) {
    const page = await asUser(browser, role);
    await openOverview(page, task);
    await expect(page.locator('main'), role).toContainText('Erreichte Punkte 10');
    await page.close();
  }
});

test.afterAll(async ({ browser }) => {
  test.setTimeout(5 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  await deleteAssignment(page, task.refId, 'E2E Team-Frist');
  await page.close();
});
