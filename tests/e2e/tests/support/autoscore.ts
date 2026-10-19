import { expect, Page, Browser, test } from '@playwright/test';
import * as fs from 'node:fs';
import { Role, createAutoScoreAssignment, editorUrl, login, openExercise } from './ilias';

/** Sample task of the browser tests, see fixtures/sample-task */
export const SAMPLE = 'fixtures/sample-task';

export interface Task {
  refId: string;
  title: string;
  assId: string;
}

export async function asUser(browser: Browser, role: Role): Promise<Page> {
  const page = await browser.newPage({ baseURL: test.info().project.use.baseURL });
  await login(page, role);
  return page;
}

/** Opens a tab of the assignment settings as tutor */
export async function tab(page: Page, task: Task, name: string) {
  await page.goto(editorUrl(task.refId, 'editAssignment', task.assId));
  // editAssignment opens the tab "Einstellungen", whose name is also part of "Korrektur-Einstellungen"
  if (name !== 'Einstellungen') {
    await page.getByRole('link', { name }).click();
  }
  await page.waitForLoadState('networkidle');
}

export async function upload(page: Page, file: string, input = page.locator('main form input[type="file"]').first()) {
  await input.setInputFiles({
    name: file.split('/').pop()!, mimeType: 'application/octet-stream', buffer: fs.readFileSync(file),
  });
}

/** Reloads a page until its main text matches, the grading service answers asynchronously */
export async function waitForText(page: Page, open: () => Promise<void>, pattern: RegExp, minutes = 8): Promise<string> {
  const end = Date.now() + minutes * 60_000;
  let text = '';
  while (Date.now() < end) {
    await open();
    text = (await page.locator('main').innerText()).replace(/\s+/g, ' ');
    if (pattern.test(text)) return text;
    await page.waitForTimeout(10_000);
  }
  throw new Error(`timeout waiting for ${pattern}, last text: ${text.slice(0, 1500)}`);
}

export async function openOverview(page: Page, task: Task) {
  await openExercise(page, task.refId);
  const link = page.getByRole('link', { name: task.title }).first();
  // after the deadline the assignment moves from "Laufende" to "Vergangene"
  if (await link.count() === 0) {
    await page.getByRole('button', { name: 'Alle', exact: true }).click();
  }
  await link.click();
  await page.waitForLoadState('networkidle');
}

export async function openSubmissionForm(page: Page, task: Task) {
  await openOverview(page, task);
  // before the first submission there is a button, afterwards the tab
  const button = page.getByRole('button', { name: 'Datei abgeben' });
  if (await button.count() > 0) {
    await button.click();
  } else {
    await page.getByRole('link', { name: 'Einreichung' }).click();
  }
}

export async function submit(page: Page, task: Task, file: string) {
  await openSubmissionForm(page, task);
  await upload(page, file);
  await page.getByRole('button', { name: 'Hochladen' }).click();
  await page.waitForLoadState('networkidle');
  const failure = page.locator('[role=status], .alert-danger').filter({ hasText: /Fehler/ });
  if (await failure.count() > 0) {
    throw new Error('Abgabe nicht eingesendet: ' + (await failure.first().innerText()).replace(/\s+/g, ' '));
  }
}

/**
 * Creates the sample task and sets up the correction at the real grading service
 * (Docker build and test of the sample solution, about one minute)
 */
export async function setUpSampleTask(page: Page, refId: string, title: string): Promise<Task> {
  const task: Task = { refId, title, assId: await createAutoScoreAssignment(page, refId, title) };

  await tab(page, task, 'Korrektur-Einstellungen');
  await upload(page, `${SAMPLE}/Dockerfile`);
  await page.getByRole('textbox', { name: 'Korrektur-Kommando' }).fill('python grade.py');
  await page.getByRole('textbox', { name: 'Punkte zum Bestehen' }).fill('5');
  await page.getByRole('button', { name: 'Einstellungen speichern' }).first().click();

  await tab(page, task, 'Bereitgestellte Dateien');
  await page.getByRole('button', { name: 'Datei hinzufügen' }).first().click();
  await upload(page, `${SAMPLE}/grade.py`);
  await page.getByRole('radio', { name: 'Einrichtung' }).check();
  await page.getByRole('button', { name: 'Datei hinzufügen' }).last().click();
  await expect(page).toHaveURL(/cmd=editFile/);

  await tab(page, task, 'Geforderte Dateien');
  await page.getByRole('button', { name: 'Datei hinzufügen' }).first().click();
  await upload(page, `${SAMPLE}/submissions/correct/aufgabe.py`);
  await page.getByRole('button', { name: 'Datei hinzufügen' }).last().click();

  await tab(page, task, 'Korrektur-Einstellungen');
  await page.getByRole('button', { name: 'Korrektur einrichten' }).click();
  await expect(page.getByText('Image will be created and tested').first()).toBeVisible();
  return task;
}

/** Waits until the sample solution has been graded after setUpSampleTask() */
export async function waitForSetup(page: Page, task: Task): Promise<string> {
  return waitForText(page, () => tab(page, task, 'Korrektur-Einstellungen'), /Erreichte Punkte/);
}

/** Value for a datetime-local field in the time zone of ILIAS (Europe/Berlin), offset in minutes from now */
export function iliasDateTime(offsetMinutes: number): string {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('de-DE', {
    timeZone: 'Europe/Berlin', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false,
  }).formatToParts(new Date(Date.now() + offsetMinutes * 60_000)).map(p => [p.type, p.value]));
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

/** Sets a fixed deadline as tutor, offset in minutes from now (negative = in the past) */
export async function setDeadline(page: Page, task: Task, offsetMinutes: number) {
  await tab(page, task, 'Einstellungen');
  await page.getByRole('radio', { name: 'Festes Datum' }).check();
  await page.getByRole('textbox', { name: /^Datum/ }).fill(iliasDateTime(offsetMinutes));
  await page.getByRole('button', { name: 'Speichern' }).first().click();
  await expect(page.getByText('Änderungen gespeichert').first()).toBeVisible();
}

export async function openGrades(page: Page, task: Task) {
  await page.goto(`ilias.php?baseClass=ilexercisehandlergui&cmdNode=d3:ms:d4&cmdClass=ilexercisemanagementgui&cmd=members&ref_id=${task.refId}&ass_id=${task.assId}`);
}

/** Sets an individual deadline in "Abgaben und Noten" for the row matching the login (or team member) */
export async function setIndividualDeadline(page: Page, task: Task, rowText: RegExp, offsetMinutes: number) {
  await openGrades(page, task);
  await page.getByRole('row', { name: rowText }).getByRole('checkbox').check();
  await page.locator('select').filter({ hasText: 'Individuellen Abgabetermin' }).first().selectOption({ label: 'Individuellen Abgabetermin setzen' });
  await page.getByRole('button', { name: 'Ausführen' }).first().click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('textbox').first().fill(iliasDateTime(offsetMinutes));
  await dialog.getByRole('button', { name: 'Speichern' }).click();
  await expect(dialog).toBeHidden();
}
