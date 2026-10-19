import { test, expect, Page } from '@playwright/test';
import { deleteAssignment, exerciseRefId } from './support/ilias';
import {
  SAMPLE, Task, asUser, openOverview, setDeadline, setUpSampleTask, submit, waitForSetup, waitForText,
} from './support/autoscore';

// Points, grade and extended feedback are only shown after the deadline; a grade the plugin
// published is withdrawn when the deadline is extended, a grade set by a tutor is kept
test.describe.configure({ mode: 'serial' });

let task: Task;

async function overviewText(page: Page): Promise<string> {
  await openOverview(page, task);
  return (await page.locator('main').innerText()).replace(/\s+/g, ' ');
}

async function openGrades(page: Page) {
  await page.goto(`ilias.php?baseClass=ilexercisehandlergui&cmdNode=d3:ms:d4&cmdClass=ilexercisemanagementgui&cmd=members&ref_id=${task.refId}&ass_id=${task.assId}`);
}

const gradeRow = (page: Page, login: string) => page.getByRole('row', { name: new RegExp(login) });

test.beforeAll(async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  const refId = exerciseRefId(test.info());
  for (const leftover of ['E2E Fristen', 'E2E Frist-Erkundung']) {
    await deleteAssignment(page, refId, leftover);
  }
  task = await setUpSampleTask(page, refId, `E2E Fristen ${Date.now()}`);
  await waitForSetup(page, task);
  await setDeadline(page, task, 24 * 60);
  await page.close();
});

test('Vor der Frist: nur die sofortige Rückmeldung, keine Bewertung', async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  for (const [role, file] of [['STUDENT1', 'correct'], ['STUDENT2', 'wrong']] as const) {
    const page = await asUser(browser, role);
    await submit(page, task, `${SAMPLE}/submissions/${file}/aufgabe.py`);
    const text = await waitForText(page, () => openOverview(page, task), /Tests bestanden/);
    expect(text).toContain('Sofortige Rückmeldung');
    for (const hidden of ['Erreichte Punkte', 'Erweitertes Feedback anzeigen', 'Musterlösung', 'Note ']) {
      expect(text, `${role} sieht vor der Frist "${hidden}"`).not.toContain(hidden);
    }
    await page.close();
  }
});

test('Nach der Frist: Punkte, Note, Status, erweitertes Feedback und Musterlösung', async ({ browser }) => {
  const tutor = await asUser(browser, 'TUTOR');
  await setDeadline(tutor, task, -60);
  await tutor.close();

  const page = await asUser(browser, 'STUDENT1');
  const text = await overviewText(page);
  for (const shown of ['Erreichte Punkte 10', 'Erweitertes Feedback anzeigen', 'Musterlösung', 'Note 10', 'Status Bestanden']) {
    expect(text).toContain(shown);
  }
  await page.close();

  const page2 = await asUser(browser, 'STUDENT2');
  const text2 = await overviewText(page2);
  expect(text2).toContain('Erreichte Punkte 7');
  expect(text2).toContain('Note 7');
  await page2.close();
});

test('Tutor sieht die veröffentlichten Bewertungen in „Abgaben und Noten“', async ({ browser }) => {
  const page = await asUser(browser, 'TUTOR');
  await openGrades(page);
  await expect(gradeRow(page, 'e2e_student1').getByRole('combobox')).toHaveValue('passed');
  await expect(gradeRow(page, 'e2e_student2').getByRole('combobox')).toHaveValue('passed');
  await page.close();
});

test('Fristverlängerung: Plugin-Note wird zurückgezogen, Tutor-Note bleibt', async ({ browser }) => {
  const tutor = await asUser(browser, 'TUTOR');
  await openGrades(tutor);
  await gradeRow(tutor, 'e2e_student1').getByRole('combobox').selectOption({ label: 'Nicht bestanden' });
  await tutor.getByRole('button', { name: 'Alle speichern' }).first().click();
  await openGrades(tutor);
  await expect(gradeRow(tutor, 'e2e_student1').getByRole('combobox')).toHaveValue('failed');
  await setDeadline(tutor, task, 24 * 60);

  // the plugin checks the grade when the student opens the assignment before the deadline
  for (const role of ['STUDENT1', 'STUDENT2'] as const) {
    const page = await asUser(browser, role);
    const text = await overviewText(page);
    expect(text).not.toContain('Erreichte Punkte');
    await page.close();
  }

  await openGrades(tutor);
  await expect(gradeRow(tutor, 'e2e_student1').getByRole('combobox'), 'Tutor-Note muss bleiben').toHaveValue('failed');
  await expect(gradeRow(tutor, 'e2e_student2').getByRole('combobox'), 'Plugin-Note muss zurückgezogen sein').toHaveValue('notgraded');
  await tutor.close();
});

test('Nach dem neuen Fristende wird die Plugin-Note wieder veröffentlicht', async ({ browser }) => {
  const tutor = await asUser(browser, 'TUTOR');
  await setDeadline(tutor, task, -60);

  const page = await asUser(browser, 'STUDENT2');
  const text = await overviewText(page);
  expect(text).toContain('Note 7');
  await page.close();

  await openGrades(tutor);
  await expect(gradeRow(tutor, 'e2e_student2').getByRole('combobox')).toHaveValue('passed');
  await expect(gradeRow(tutor, 'e2e_student1').getByRole('combobox'), 'Tutor-Note bleibt').toHaveValue('failed');
  await tutor.close();
});

test.afterAll(async ({ browser }) => {
  test.setTimeout(5 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  await deleteAssignment(page, task.refId, 'E2E Fristen');
  await page.close();
});
