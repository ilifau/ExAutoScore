import { test, expect, Page } from '@playwright/test';
import { editorUrl, exerciseRefId, login } from './support/ilias';

// Plugin configuration (stage 2 of the UI migration). The test leaves the configuration as it was.
test.describe.configure({ mode: 'serial' });

const CREATOR_ROLE = process.env.E2E_CREATOR_ROLE ?? 'il_crs_admin_93';
const PLUGINS_REF_ID = process.env.ILIAS11_PLUGINS_REF_ID ?? '31';

const rolesGroup = (page: Page) => page.getByRole('group').filter({ hasText: 'Erzeuger-Rollen' });

async function openConfig(page: Page) {
  await page.goto(`ilias.php?baseClass=ilAdministrationGUI&ref_id=${PLUGINS_REF_ID}&cmd=jump`);
  const heading = page.getByRole('heading', { name: 'ExAutoScore AssignmentHook' });
  // the actions button of the plugin item has no visible text, only an aria-label
  const item = heading.locator('xpath=ancestor::*[.//button[@aria-label="Aktionen" or normalize-space()="Aktionen"]][1]');
  await item.getByRole('button', { name: 'Aktionen' }).click();
  await page.getByRole('button', { name: 'Konfigurieren' }).click();
  await expect(page.getByRole('button', { name: 'Speichern' })).toBeVisible();
  // the tag field is built by JavaScript after loading
  await page.waitForLoadState('networkidle');
  await expect(rolesGroup(page).locator('.tagify__input')).toBeVisible();
}

async function roleTags(page: Page): Promise<string[]> {
  return (await rolesGroup(page).locator('.tagify__tag-text').allInnerTexts()).map(t => t.trim()).filter(Boolean);
}

async function addRole(page: Page, title: string) {
  const input = rolesGroup(page).locator('.tagify__input');
  await input.click();
  await input.pressSequentially(title, { delay: 50 });
  await input.press('Enter');
  await expect(rolesGroup(page).locator('.tagify__tag-text').filter({ hasText: title })).toBeVisible();
}

async function removeRole(page: Page, title: string) {
  await rolesGroup(page).locator('.tagify__tag').filter({ hasText: title }).locator('.tagify__tag__removeBtn').click();
}

test.beforeEach(async ({ page }) => {
  await login(page, 'ADMIN');
});

test('Konfiguration zeigt alle Felder und die Erzeuger-Rolle', async ({ page }) => {
  await openConfig(page);
  for (const label of ['Abgabe-URL', 'API-Key', 'Timeout', 'Erzeuger-Rollen', 'Pack-Kommando', 'Entpack-Kommando']) {
    await expect(page.getByRole('group').filter({ hasText: label }).first()).toBeVisible();
  }
  expect(await roleTags(page)).toEqual([CREATOR_ROLE]);
});

test('Unbekannte Rolle wird mit Meldung abgelehnt und nicht gespeichert', async ({ page }) => {
  await openConfig(page);
  await addRole(page, 'gibtsnicht');
  expect(await roleTags(page), 'eingetippte Rolle bleibt im Feld stehen').toContain('gibtsnicht');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByText('Unbekannte Rolle(n): gibtsnicht').first()).toBeVisible();
  await openConfig(page);
  expect(await roleTags(page)).toEqual([CREATOR_ROLE]);
});

test('Rolle hinzufügen und wieder entfernen, beides wird gespeichert', async ({ page }) => {
  await openConfig(page);
  // "Guest" has no test users; "User" would briefly make the students creators and disturb parallel tests
  await addRole(page, 'Guest');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await openConfig(page);
  expect(await roleTags(page)).toEqual([CREATOR_ROLE, 'Guest']);

  await removeRole(page, 'Guest');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await openConfig(page);
  expect(await roleTags(page)).toEqual([CREATOR_ROLE]);
});

test('Tutor ohne Erzeuger-Rolle kann keine Programmieraufgabe anlegen', async ({ page }, info) => {
  const refId = exerciseRefId(info);
  await page.context().clearCookies();
  await login(page, 'NOCREATOR');
  await page.goto(editorUrl(refId));
  const types = page.getByRole('combobox', { name: 'Abgabetyp' });
  await expect(types).toBeVisible();
  const options = await types.locator('option').allInnerTexts();
  expect(options).toContain('Datei');
  expect(options.join('|')).not.toContain('Programmieraufgabe');
});

test.afterAll(async ({ browser }) => {
  // restore the creator roles if a test stopped half-way
  const page = await browser.newPage({ baseURL: test.info().project.use.baseURL });
  await login(page, 'ADMIN');
  await openConfig(page);
  const tags = await roleTags(page);
  if (tags.join() !== CREATOR_ROLE) {
    for (const tag of tags.filter(t => t !== CREATOR_ROLE)) {
      await removeRole(page, tag);
    }
    if (!tags.includes(CREATOR_ROLE)) {
      await addRole(page, CREATOR_ROLE);
    }
    await page.getByRole('button', { name: 'Speichern' }).click();
  }
  await page.close();
});
