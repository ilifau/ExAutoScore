import { test, expect, Page, Browser } from '@playwright/test';
import * as fs from 'node:fs';
import { Role, createAutoScoreAssignment, deleteAssignment, editorUrl, exerciseRefId, login, openExercise } from './support/ilias';

// Runs against the real grading service with the sample task in fixtures/sample-task
test.describe.configure({ mode: 'serial' });

const F = 'fixtures/sample-task';
const title = `E2E Bewertungsdienst ${Date.now()}`;
let refId = '';
let assId = '';

async function tab(page: Page, name: string) {
  await page.goto(editorUrl(refId, 'editAssignment', assId));
  await page.getByRole('link', { name }).click();
  await page.waitForLoadState('networkidle');
}

async function upload(page: Page, file: string) {
  await page.locator('main form input[type="file"]').setInputFiles({
    name: file.split('/').pop()!, mimeType: 'application/octet-stream', buffer: fs.readFileSync(file),
  });
}

/** Reloads a page until its main text matches, the grading service answers asynchronously */
async function waitForText(page: Page, open: () => Promise<void>, pattern: RegExp, minutes = 8): Promise<string> {
  const end = Date.now() + minutes * 60_000;
  let text = '';
  while (Date.now() < end) {
    await open();
    text = (await page.locator('main').innerText()).replace(/\s+/g, ' ');
    if (pattern.test(text)) return text;
    await page.waitForTimeout(20_000);
  }
  throw new Error(`timeout waiting for ${pattern}, last text: ${text.slice(0, 1500)}`);
}

async function asUser(browser: Browser, role: Role): Promise<Page> {
  const page = await browser.newPage({ baseURL: test.info().project.use.baseURL });
  await login(page, role);
  return page;
}

async function openSubmissionForm(page: Page) {
  await openExercise(page, refId);
  await page.getByRole('link', { name: title }).first().click();
  // before the first submission there is a button, afterwards the tab
  const button = page.getByRole('button', { name: 'Datei abgeben' });
  if (await button.count() > 0) {
    await button.click();
  } else {
    await page.getByRole('link', { name: 'Einreichung' }).click();
  }
}

async function submit(page: Page, file: string) {
  await openSubmissionForm(page);
  await upload(page, file);
  await page.getByRole('button', { name: 'Hochladen' }).click();
  await page.waitForLoadState('networkidle');
  const failure = page.locator('[role=status], .alert-danger').filter({ hasText: /Fehler/ });
  if (await failure.count() > 0) {
    throw new Error('Abgabe nicht eingesendet: ' + (await failure.first().innerText()).replace(/\s+/g, ' '));
  }
}

async function openOverview(page: Page) {
  await openExercise(page, refId);
  await page.getByRole('link', { name: title }).first().click();
  await page.waitForLoadState('networkidle');
}

test.beforeAll(async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  refId = exerciseRefId(test.info());
  const page = await asUser(browser, 'TUTOR');
  await deleteAssignment(page, refId, 'E2E Bewertungsdienst');
  await deleteAssignment(page, refId, 'E2E-Erkundung');
  assId = await createAutoScoreAssignment(page, refId, title);

  await tab(page, 'Korrektur-Einstellungen');
  await upload(page, `${F}/Dockerfile`);
  await page.getByRole('textbox', { name: 'Korrektur-Kommando' }).fill('python grade.py');
  await page.getByRole('textbox', { name: 'Punkte zum Bestehen' }).fill('5');
  await page.getByRole('button', { name: 'Einstellungen speichern' }).first().click();

  for (const f of ['grade.py']) {
    await tab(page, 'Bereitgestellte Dateien');
    await page.getByRole('button', { name: 'Datei hinzufügen' }).first().click();
    await upload(page, `${F}/${f}`);
    await page.getByRole('radio', { name: 'Einrichtung' }).check();
    await page.getByRole('button', { name: 'Datei hinzufügen' }).last().click();
    await expect(page).toHaveURL(/cmd=editFile/);
  }
  await tab(page, 'Geforderte Dateien');
  await page.getByRole('button', { name: 'Datei hinzufügen' }).first().click();
  await upload(page, `${F}/submissions/correct/aufgabe.py`);
  await page.getByRole('button', { name: 'Datei hinzufügen' }).last().click();

  await tab(page, 'Korrektur-Einstellungen');
  await page.getByRole('button', { name: 'Korrektur einrichten' }).click();
  await expect(page.getByText('Image will be created and tested').first()).toBeVisible();
  await page.close();
});

test('Einrichtung: Musterlösung erhält 10 von 10 Punkten', async ({ page }) => {
  test.setTimeout(10 * 60_000);
  await login(page, 'TUTOR');
  const text = await waitForText(page, () => tab(page, 'Korrektur-Einstellungen'), /Erreichte Punkte/);
  expect(text).toContain('Erreichte Punkte 10');
  expect(text).toContain('6/6 Tests bestanden');
});

test('Erweitertes Feedback wird formatiert und ohne Skripte angezeigt', async ({ page }) => {
  await login(page, 'TUTOR');
  await tab(page, 'Korrektur-Einstellungen');
  await page.getByRole('button', { name: 'Erweitertes Feedback anzeigen' }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toContainText('fakultaet(0) == 1');
  await expect(dialog.getByRole('heading', { name: /Result: PASSED/ })).toBeVisible();
  await expect(dialog.locator('p[style*="pre-wrap"]').first()).toBeVisible();
  await expect(dialog.locator('script')).toHaveCount(0);
});

test('Studentin 1 gibt die richtige Lösung ab und erhält 10 Punkte', async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'STUDENT1');
  await submit(page, `${F}/submissions/correct/aufgabe.py`);
  const text = await waitForText(page, () => openOverview(page), /Tests bestanden/);
  expect(text).toContain('Punkte: 10.0 / 10.0 (6/6 Tests bestanden)');
  await page.close();
});

test('Studentin 2 gibt eine fehlerhafte Lösung ab und erhält 7 Punkte', async ({ browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await asUser(browser, 'STUDENT2');
  await submit(page, `${F}/submissions/wrong/aufgabe.py`);
  const text = await waitForText(page, () => openOverview(page), /Tests bestanden/);
  expect(text).toContain('Punkte: 7.0 / 10.0 (4/6 Tests bestanden)');
  expect(text).toContain('FEHLER [0.0/2.0] fakultaet(0) == 1');
  await page.close();
});

test('Abgabe mit nicht freigeschalteter Dateiendung wird mit Hinweis abgelehnt', async ({ browser }) => {
  const tutor = await asUser(browser, 'TUTOR');
  await tab(tutor, 'Geforderte Dateien');
  await tutor.getByRole('button', { name: 'Datei hinzufügen' }).first().click();
  await tutor.locator('main form input[type="file"]').setInputFiles({ name: 'zusatz.xyz', mimeType: 'text/plain', buffer: Buffer.from('x\n') });
  await tutor.getByRole('button', { name: 'Datei hinzufügen' }).last().click();
  await tutor.close();

  const page = await asUser(browser, 'STUDENT1');
  await openSubmissionForm(page);
  const inputs = page.locator('main form input[type="file"]');
  await inputs.nth(0).setInputFiles({ name: 'aufgabe.py', mimeType: 'text/x-python', buffer: fs.readFileSync(`${F}/submissions/correct/aufgabe.py`) });
  await inputs.nth(1).setInputFiles({ name: 'zusatz.xyz', mimeType: 'text/plain', buffer: Buffer.from('y\n') });
  await page.getByRole('button', { name: 'Hochladen' }).click();
  await expect(page.getByText('Die Dateiendung .xyz ist in ILIAS nicht freigeschaltet').first()).toBeVisible();
  await expect(page.getByText('Beim Einsenden Ihrer Dateien an den Korrekturservice trat ein Fehler auf')).toHaveCount(0);
  await page.close();
});

test.afterAll(async ({ browser }) => {
  test.setTimeout(5 * 60_000);
  const page = await asUser(browser, 'TUTOR');
  await deleteAssignment(page, refId, 'E2E Bewertungsdienst');
  await page.close();
});
