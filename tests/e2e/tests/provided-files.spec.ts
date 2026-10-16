import { test, expect, Page } from '@playwright/test';
import { createAutoScoreAssignment, deleteAssignment, editorUrl, exerciseRefId, login } from './support/ilias';

test.describe.configure({ mode: 'default' });

const title = `E2E Bereitgestellte Dateien ${Date.now()}`;
let refId = '';
let assId = '';

async function openTab(page: Page) {
  await page.goto(editorUrl(refId, 'editAssignment', assId));
  await page.getByRole('link', { name: 'Bereitgestellte Dateien' }).click();
}

async function addFile(page: Page, name: string, purpose: string) {
  await openTab(page);
  await page.getByRole('button', { name: 'Datei hinzufügen' }).first().click();
  await page.locator('main form input[type="file"]').setInputFiles({ name, mimeType: 'text/plain', buffer: Buffer.from(`Inhalt von ${name}`) });
  await page.getByRole('radio', { name: purpose }).check();
  await page.getByRole('button', { name: 'Datei hinzufügen' }).last().click();
  await expect(page).toHaveURL(/cmd=editFile/);
}

const table = (page: Page) => page.getByRole('grid', { name: 'Bereitgestellte Dateien' });
const fileRow = (page: Page, name: string) => table(page).getByRole('row', { name: new RegExp(`^${name.replace('.', '\\.')} `) });

async function setUp(page: Page) {
  await login(page, 'TUTOR');
  await deleteAssignment(page, refId, 'E2E Bereitgestellte Dateien');
  assId = await createAutoScoreAssignment(page, refId, title);
  await addFile(page, 'b_korrektur.py', 'Korrektur');
  await addFile(page, 'a_einrichtung.txt', 'Einrichtung');
  await addFile(page, 'c_ignoriert.sh', 'Ignoriert');
}

test.beforeAll(async ({ browser }) => {
  refId = exerciseRefId(test.info());
  const page = await browser.newPage({ baseURL: test.info().project.use.baseURL });
  await setUp(page);
  await page.close();
});

test.beforeEach(async ({ page }) => {
  await login(page, 'TUTOR');
});

test('Aufgabe mit drei Dateien: Tabelle zeigt sie mit Verwendung', async ({ page }) => {
  await openTab(page);
  await expect(table(page).getByRole('row')).toHaveCount(1 + 3 + 1);
  await expect(fileRow(page, 'a_einrichtung.txt')).toContainText('Einrichtung');
  await expect(fileRow(page, 'b_korrektur.py')).toContainText('Korrektur');
  await expect(fileRow(page, 'c_ignoriert.sh')).toContainText('Ignoriert');
});

test('Sortieren nach Dateiname', async ({ page }) => {
  await openTab(page);
  const names = async () => (await table(page).locator('tbody tr td:nth-child(2)').allInnerTexts()).map(s => s.trim()).filter(Boolean);
  await table(page).getByRole('button', { name: 'Dateiname' }).click();
  await page.waitForLoadState('networkidle');
  const first = await names();
  await table(page).getByRole('button', { name: 'Dateiname' }).click();
  await page.waitForLoadState('networkidle');
  const second = await names();
  expect([...first].sort()).toEqual(first.length ? [...first].sort() : first);
  expect(second).toEqual([...first].reverse());
});

test('Download über den Dateinamen', async ({ page }) => {
  await openTab(page);
  const download = page.waitForEvent('download');
  await fileRow(page, 'a_einrichtung.txt').getByRole('link').click();
  const file = await download;
  expect(file.suggestedFilename()).toBe('a_einrichtung.txt');
});

test('Datei bearbeiten über die Zeilenaktion', async ({ page }) => {
  await openTab(page);
  await fileRow(page, 'c_ignoriert.sh').getByRole('button', { name: 'Aktionen' }).click();
  await page.getByRole('button', { name: 'Datei bearbeiten' }).or(page.getByRole('link', { name: 'Datei bearbeiten' })).first().click();
  await expect(page.getByRole('heading', { name: 'Datei bearbeiten' })).toBeVisible();
  await page.getByRole('textbox', { name: 'Beschreibung' }).fill('Vorlage für Studierende');
  await page.getByRole('button', { name: 'Datei aktualisieren' }).first().click();
  await openTab(page);
  await expect(fileRow(page, 'c_ignoriert.sh')).toContainText('Vorlage für Studierende');
});

test('Unbekannte Datei-ID führt zu einer Meldung statt einem Fehler', async ({ page }) => {
  await openTab(page);
  const url = new URL(page.url());
  url.searchParams.set('cmd', 'editFile');
  url.searchParams.set('id', '999999');
  url.searchParams.set('cmdClass', 'ilExAutoScoreProvidedFilesGUI');
  await page.goto(url.toString());
  await expect(page.getByText('Kein Zugriffsrecht').first()).toBeVisible();
});

test('Dateien löschen fragt im Dialog nach, Abbrechen löscht nichts', async ({ page }) => {
  await openTab(page);
  await fileRow(page, 'a_einrichtung.txt').getByRole('checkbox').check();
  await fileRow(page, 'b_korrektur.py').getByRole('checkbox').check();
  await table(page).getByRole('button', { name: 'Sammelaktionen' }).click();
  await page.getByRole('button', { name: 'Dateien löschen' }).first().click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toContainText('a_einrichtung.txt');
  await expect(dialog).toContainText('b_korrektur.py');
  await expect(dialog).not.toContainText('c_ignoriert.sh');
  await dialog.getByRole('button', { name: 'Abbrechen' }).last().click();
  await openTab(page);
  await expect(table(page).getByRole('row')).toHaveCount(1 + 3 + 1);
});

test('Dateien löschen entfernt genau die ausgewählten', async ({ page }) => {
  await openTab(page);
  await fileRow(page, 'a_einrichtung.txt').getByRole('checkbox').check();
  await fileRow(page, 'b_korrektur.py').getByRole('checkbox').check();
  await table(page).getByRole('button', { name: 'Sammelaktionen' }).click();
  await page.getByRole('button', { name: 'Dateien löschen' }).first().click();
  await page.getByRole('dialog').getByRole('button', { name: 'Löschen' }).click();
  await expect(page.getByText('Die Datei(en) wurden gelöscht').first()).toBeVisible();
  await expect(fileRow(page, 'a_einrichtung.txt')).toHaveCount(0);
  await expect(fileRow(page, 'b_korrektur.py')).toHaveCount(0);
  await expect(fileRow(page, 'c_ignoriert.sh')).toHaveCount(1);
});

test.afterAll(async ({ browser }) => {
  const page = await browser.newPage({ baseURL: test.info().project.use.baseURL });
  await login(page, 'TUTOR');
  for (const leftover of ['E2E Bereitgestellte Dateien', 'E2E-Erkundung']) {
    await deleteAssignment(page, refId, leftover);
  }
  await page.goto(editorUrl(refId));
  await expect(page.getByRole('row', { name: /E2E Bereitgestellte Dateien|E2E-Erkundung/ })).toHaveCount(0);
  await page.close();
});
