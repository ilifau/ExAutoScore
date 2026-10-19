import { test, expect } from '@playwright/test';
import { deleteAssignment, exerciseRefId } from './support/ilias';
import {
  SAMPLE, Task, asUser, openOverview, openSubmissionForm, setUpSampleTask, submit, tab, upload, waitForSetup, waitForText,
} from './support/autoscore';

// Runs against the real grading service with the sample task in fixtures/sample-task
test.describe.configure({ mode: 'serial' });

let task: Task;

test.beforeAll(async ({ browser }) => {
  test.setTimeout(5 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  const refId = exerciseRefId(test.info());
  await deleteAssignment(page, refId, 'E2E Bewertungsdienst');
  await deleteAssignment(page, refId, 'E2E-Erkundung');
  task = await setUpSampleTask(page, refId, `E2E Bewertungsdienst ${Date.now()}`);
  await page.close();
});

test('Einrichtung: Musterlösung erhält 10 von 10 Punkten', async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  const text = await waitForSetup(page, task);
  expect(text).toContain('Erreichte Punkte 10');
  expect(text).toContain('6/6 Tests bestanden');
  await page.close();
});

test('Erweitertes Feedback wird formatiert und ohne Skripte angezeigt', async ({ browser }) => {
  const page = await asUser(browser, 'TUTOR');
  await tab(page, task, 'Korrektur-Einstellungen');
  await page.getByRole('button', { name: 'Erweitertes Feedback anzeigen' }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toContainText('fakultaet(0) == 1');
  await expect(dialog.getByRole('heading', { name: /Result: PASSED/ })).toBeVisible();
  await expect(dialog.locator('p[style*="pre-wrap"]').first()).toBeVisible();
  await expect(dialog.locator('script')).toHaveCount(0);
  await page.close();
});

test('Studentin 1 gibt die richtige Lösung ab und erhält 10 Punkte', async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'STUDENT1');
  await submit(page, task, `${SAMPLE}/submissions/correct/aufgabe.py`);
  const text = await waitForText(page, () => openOverview(page, task), /Tests bestanden/);
  expect(text).toContain('Punkte: 10.0 / 10.0 (6/6 Tests bestanden)');
  await page.close();
});

test('Studentin 2 gibt eine fehlerhafte Lösung ab und erhält 7 Punkte', async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'STUDENT2');
  await submit(page, task, `${SAMPLE}/submissions/wrong/aufgabe.py`);
  const text = await waitForText(page, () => openOverview(page, task), /Tests bestanden/);
  expect(text).toContain('Punkte: 7.0 / 10.0 (4/6 Tests bestanden)');
  expect(text).toContain('FEHLER [0.0/2.0] fakultaet(0) == 1');
  await page.close();
});

test('Tutor: „Alle Abgaben korrigieren“ fragt im Dialog nach und sendet erneut', async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  await tab(page, task, 'Korrektur-Einstellungen');
  await page.getByRole('button', { name: 'Alle Abgaben korrigieren' }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toContainText('Möchten Sie alle Abgaben erneut an den Korrekturservice senden?');
  await dialog.getByRole('button', { name: 'Senden' }).click();
  await expect(page.getByText('Alle Abgaben wurden an den Korrekturservice gesandt.').first()).toBeVisible();
  await page.close();

  // the results come back again
  const student = await asUser(browser, 'STUDENT1');
  const text = await waitForText(student, () => openOverview(student, task), /Tests bestanden/);
  expect(text).toContain('Punkte: 10.0 / 10.0 (6/6 Tests bestanden)');
  await student.close();
});

test('Studentin: „Einreichung löschen“ fragt im Dialog nach und löscht die Abgabe', async ({ browser }) => {
  const page = await asUser(browser, 'STUDENT2');
  await openSubmissionForm(page, task);
  await page.getByRole('button', { name: 'Einreichung löschen' }).first().click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toContainText('Möchten Sie die Einreichung und alle hochgeladenen Dateien löschen?');
  await dialog.getByRole('button', { name: 'Einreichung löschen' }).click();
  await expect(page.getByText('Die Einreichung wurde gelöscht und Ihr Status zurückgesetzt.').first()).toBeVisible();
  await openOverview(page, task);
  await expect(page.locator('main')).toContainText('Bisher keine Abgabe');
  await page.close();
});

test('Abgabe mit nicht freigeschalteter Dateiendung wird mit Hinweis abgelehnt', async ({ browser }) => {
  const tutor = await asUser(browser, 'TUTOR');
  await tab(tutor, task, 'Geforderte Dateien');
  await tutor.getByRole('button', { name: 'Datei hinzufügen' }).first().click();
  await tutor.locator('main form input[type="file"]').setInputFiles({ name: 'zusatz.xyz', mimeType: 'text/plain', buffer: Buffer.from('x\n') });
  await tutor.getByRole('button', { name: 'Datei hinzufügen' }).last().click();
  await tutor.close();

  const page = await asUser(browser, 'STUDENT1');
  await openSubmissionForm(page, task);
  const inputs = page.locator('main form input[type="file"]');
  await upload(page, `${SAMPLE}/submissions/correct/aufgabe.py`, inputs.nth(0));
  await inputs.nth(1).setInputFiles({ name: 'zusatz.xyz', mimeType: 'text/plain', buffer: Buffer.from('y\n') });
  await page.getByRole('button', { name: 'Hochladen' }).click();
  await expect(page.getByText('Die Dateiendung .xyz ist in ILIAS nicht freigeschaltet').first()).toBeVisible();
  await expect(page.getByText('Beim Einsenden Ihrer Dateien an den Korrekturservice trat ein Fehler auf')).toHaveCount(0);
  await page.close();
});

test.afterAll(async ({ browser }) => {
  test.setTimeout(5 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  await deleteAssignment(page, task.refId, 'E2E Bewertungsdienst');
  await page.close();
});
