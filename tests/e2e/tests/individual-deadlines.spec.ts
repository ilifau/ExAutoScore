import { test, expect } from '@playwright/test';
import { deleteAssignment, exerciseRefId } from './support/ilias';
import {
  SAMPLE, Task, asUser, openOverview, setDeadline, setIndividualDeadline, setUpSampleTask, submit, waitForSetup, waitForText,
} from './support/autoscore';

// An individual deadline extends the submission phase of one student: her result stays hidden
test.describe.configure({ mode: 'serial' });

let task: Task;

test.beforeAll(async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  const refId = exerciseRefId(test.info());
  for (const leftover of ['E2E Individuelle Frist', 'E2E IDL-Erkundung']) {
    await deleteAssignment(page, refId, leftover);
  }
  task = await setUpSampleTask(page, refId, `E2E Individuelle Frist ${Date.now()}`);
  await waitForSetup(page, task);
  await setDeadline(page, task, 60);
  await page.close();
});

test('Beide geben vor der Frist ab', async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  for (const role of ['STUDENT1', 'STUDENT2'] as const) {
    const page = await asUser(browser, role);
    await submit(page, task, `${SAMPLE}/submissions/correct/aufgabe.py`);
    await waitForText(page, () => openOverview(page, task), /Tests bestanden/);
    await page.close();
  }
});

test('Nach der allgemeinen Frist: nur Studentin 1 sieht ihre Bewertung, Studentin 2 hat noch Zeit', async ({ browser }) => {
  const tutor = await asUser(browser, 'TUTOR');
  await setIndividualDeadline(tutor, task, /e2e_student2/, 24 * 60);
  await setDeadline(tutor, task, -60);
  await tutor.close();

  const student1 = await asUser(browser, 'STUDENT1');
  await openOverview(student1, task);
  await expect(student1.locator('main')).toContainText('Erreichte Punkte 10');
  await student1.close();

  const student2 = await asUser(browser, 'STUDENT2');
  await openOverview(student2, task);
  const main = student2.locator('main');
  await expect(main).toContainText('Sofortige Rückmeldung');
  await expect(main).not.toContainText('Erreichte Punkte');
  await expect(main).not.toContainText('Erweitertes Feedback anzeigen');
  await student2.close();
});

test.afterAll(async ({ browser }) => {
  test.setTimeout(5 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  await deleteAssignment(page, task.refId, 'E2E Individuelle Frist');
  await page.close();
});
