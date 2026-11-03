import { test, expect } from '@playwright/test';
import { deleteAssignment, exerciseRefId } from './support/ilias';
import { SAMPLE, Task, asUser, openOverview, setDeadline, setUpSampleTask, submit, tab, waitForSetup, waitForText } from './support/autoscore';

// Option "Musterlösung in der Übersicht ausblenden": no link and no download, also with a known link
test.describe.configure({ mode: 'serial' });

const HIDE = 'Musterlösung in der Übersicht ausblenden';
const SOLUTION = 'def ';
let task: Task;
let downloadUrl = '';

test.beforeAll(async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  const refId = exerciseRefId(test.info());
  await deleteAssignment(page, refId, 'E2E Musterloesung');
  task = await setUpSampleTask(page, refId, `E2E Musterloesung ${Date.now()}`);
  await waitForSetup(page, task);
  await setDeadline(page, task, 24 * 60);
  await page.close();

  const student = await asUser(browser, 'STUDENT1');
  await submit(student, task, `${SAMPLE}/submissions/correct/aufgabe.py`);
  await waitForText(student, () => openOverview(student, task), /Tests bestanden/);
  await student.close();

  const tutor = await asUser(browser, 'TUTOR');
  await setDeadline(tutor, task, -60);
  await tutor.close();
});

test('Ohne die Option: Link sichtbar, Download möglich', async ({ browser }) => {
  const page = await asUser(browser, 'STUDENT1');
  await openOverview(page, task);
  const link = page.locator('main a[href*="downloadExampleFile"]').first();
  await expect(link).toBeVisible();
  downloadUrl = new URL((await link.getAttribute('href')) ?? '', page.url()).toString();
  expect(await (await page.request.get(downloadUrl)).text()).toContain(SOLUTION);
  await page.close();
});

test('Mit der Option: kein Link und auch der bekannte Link liefert die Datei nicht', async ({ browser }) => {
  const tutor = await asUser(browser, 'TUTOR');
  await tab(tutor, task, 'Korrektur-Einstellungen');
  await tutor.getByRole('checkbox', { name: HIDE }).check();
  await tutor.getByRole('button', { name: 'Einstellungen speichern' }).first().click();
  await tab(tutor, task, 'Korrektur-Einstellungen');
  await expect(tutor.getByRole('checkbox', { name: HIDE })).toBeChecked();
  await tutor.close();

  const page = await asUser(browser, 'STUDENT1');
  await openOverview(page, task);
  await expect(page.locator('main a[href*="downloadExampleFile"]')).toHaveCount(0);
  const answer = await page.request.get(downloadUrl);
  expect(answer.headers()['content-disposition'] ?? '').not.toContain('aufgabe.py');
  expect(await answer.text()).not.toContain(SOLUTION);
  await page.close();
});
