import { test, expect, Page } from '@playwright/test';
import { createAutoScoreAssignment, deleteAssignment, editorUrl, exerciseRefId, login } from './support/ilias';

// Tab "Geforderte Dateien": files the students have to submit, each with a sample solution
const title = `E2E Geforderte Dateien ${Date.now()}`;
let refId = '';
let assId = '';

const table = (page: Page) => page.getByRole('grid', { name: 'Geforderte Dateien' });
const fileRow = (page: Page, name: string) => table(page).getByRole('row', { name: new RegExp(`^${name.replace('.', '\\.')} `) });

async function openTab(page: Page) {
  await page.goto(editorUrl(refId, 'editAssignment', assId));
  await page.getByRole('link', { name: 'Geforderte Dateien' }).click();
}

async function addFile(page: Page, name: string, encoding: string, maxKb: string, content: string) {
  await openTab(page);
  await page.getByRole('button', { name: 'Datei hinzufügen' }).first().click();
  await page.locator('main form input[type="file"]').setInputFiles({ name, mimeType: 'text/plain', buffer: Buffer.from(content) });
  await page.getByRole('combobox', { name: 'Erlaubte Kodierung' }).selectOption({ label: encoding });
  await page.getByRole('textbox', { name: 'Max. Größe' }).fill(maxKb);
  await page.getByRole('button', { name: 'Datei hinzufügen' }).last().click();
  await expect(page).toHaveURL(/cmd=editFile/);
}

test.beforeAll(async ({ browser }) => {
  refId = exerciseRefId(test.info());
  const page = await browser.newPage({ baseURL: test.info().project.use.baseURL });
  await login(page, 'TUTOR');
  await deleteAssignment(page, refId, 'E2E Geforderte Dateien');
  assId = await createAutoScoreAssignment(page, refId, title);
  await addFile(page, 'aufgabe.py', 'UTF-8', '10', 'def summe(z):\n    return sum(z)\n');
  await addFile(page, 'notizen.txt', 'ASCII', '2', 'Kurze Notiz\n');
  await page.close();
});

test.beforeEach(async ({ page }) => {
  await login(page, 'TUTOR');
});

test('Tabelle zeigt Dateiname, Kodierung und Größen', async ({ page }) => {
  await openTab(page);
  await expect(table(page).getByRole('row')).toHaveCount(1 + 2 + 1);
  await expect(fileRow(page, 'aufgabe.py')).toContainText('UTF-8');
  await expect(fileRow(page, 'aufgabe.py')).toContainText('10 KB');
  await expect(fileRow(page, 'notizen.txt')).toContainText('ASCII');
});

test('Download der Musterlösung über den Dateinamen', async ({ page }) => {
  await openTab(page);
  const download = page.waitForEvent('download');
  await fileRow(page, 'aufgabe.py').getByRole('link').click();
  expect((await download).suggestedFilename()).toBe('aufgabe.py');
});

test('Datei bearbeiten: Beschreibung und Größe werden gespeichert', async ({ page }) => {
  await openTab(page);
  await fileRow(page, 'notizen.txt').getByRole('button', { name: 'Aktionen' }).click();
  await page.getByRole('button', { name: 'Datei bearbeiten' }).or(page.getByRole('link', { name: 'Datei bearbeiten' })).first().click();
  await page.getByRole('textbox', { name: 'Max. Größe' }).fill('5');
  await page.getByRole('textbox', { name: 'Beschreibung' }).fill('Freiwillige Notizen');
  await page.getByRole('button', { name: 'Datei aktualisieren' }).first().click();
  await openTab(page);
  await expect(fileRow(page, 'notizen.txt')).toContainText('5 KB');
  await expect(fileRow(page, 'notizen.txt')).toContainText('Freiwillige Notizen');
});

test('Datei löschen über den Dialog', async ({ page }) => {
  await openTab(page);
  await fileRow(page, 'notizen.txt').getByRole('checkbox').check();
  await table(page).getByRole('button', { name: 'Sammelaktionen' }).click();
  await page.getByRole('button', { name: 'Dateien löschen' }).first().click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toContainText('notizen.txt');
  await expect(dialog).not.toContainText('aufgabe.py');
  await dialog.getByRole('button', { name: 'Löschen' }).click();
  await expect(page.getByText('Die Datei(en) wurden gelöscht').first()).toBeVisible();
  await expect(fileRow(page, 'notizen.txt')).toHaveCount(0);
  await expect(fileRow(page, 'aufgabe.py')).toHaveCount(1);
});

test.afterAll(async ({ browser }) => {
  const page = await browser.newPage({ baseURL: test.info().project.use.baseURL });
  await login(page, 'TUTOR');
  await deleteAssignment(page, refId, 'E2E Geforderte Dateien');
  await page.close();
});
