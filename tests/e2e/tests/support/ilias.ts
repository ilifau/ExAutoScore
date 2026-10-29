import { Page, TestInfo, test } from '@playwright/test';

export type Role = 'ADMIN' | 'TUTOR' | 'STUDENT1' | 'STUDENT2' | 'NOCREATOR';

export function credentials(role: Role) {
  return {
    login: process.env[`E2E_${role}_LOGIN`] ?? '',
    password: process.env[`E2E_${role}_PASSWORD`] ?? '',
  };
}

export function exerciseRefId(info: TestInfo): string {
  const refId = process.env[`${info.project.name.toUpperCase()}_EXC_REF_ID`] ?? '';
  test.skip(refId === '', `keine Test-Uebung fuer ${info.project.name} in .env`);
  return refId;
}

export async function login(page: Page, role: Role): Promise<void> {
  const { login, password } = credentials(role);
  await page.goto('login.php');
  const passwordField = page.locator('input[type="password"]');
  await page.locator('form:has(input[type="password"]) input[type="text"]').first().fill(login);
  await passwordField.fill(password);
  await passwordField.press('Enter');
  await page.waitForLoadState('networkidle');
}

export async function openExercise(page: Page, refId: string): Promise<void> {
  await page.goto(`goto.php?target=exc_${refId}`);
}

// cmdNode values differ between ILIAS instances, so they are read once from the exercise tabs
const cmdNodes: Record<string, string> = {};

async function cmdNode(page: Page, refId: string, tab: string): Promise<string> {
  const key = `${test.info().project.name}:${tab}`;
  if (!cmdNodes[key]) {
    await openExercise(page, refId);
    const href = await page.getByRole('link', { name: tab, exact: true }).first().getAttribute('href') ?? '';
    cmdNodes[key] = new URL(href, page.url()).searchParams.get('cmdNode') ?? '';
  }
  return cmdNodes[key];
}

export async function editorUrl(page: Page, refId: string, cmd = 'listAssignments', assId?: string): Promise<string> {
  return `ilias.php?baseClass=ilexercisehandlergui&cmdNode=${await cmdNode(page, refId, 'Bearbeiten')}&cmdClass=ilExAssignmentEditorGUI&cmd=${cmd}&ref_id=${refId}`
    + (assId ? `&ass_id=${assId}` : '');
}

export async function membersUrl(page: Page, refId: string, assId: string): Promise<string> {
  return `ilias.php?baseClass=ilexercisehandlergui&cmdNode=${await cmdNode(page, refId, 'Abgaben und Noten')}&cmdClass=ilexercisemanagementgui&cmd=members&ref_id=${refId}&ass_id=${assId}`;
}

/** Creates an AutoScore assignment without deadline and returns its id */
export async function createAutoScoreAssignment(page: Page, refId: string, title: string, team = false): Promise<string> {
  await page.goto(await editorUrl(page, refId));
  await page.getByRole('combobox', { name: 'Abgabetyp' })
    .selectOption({ label: team ? 'Programmieraufgabe (als Team)' : 'Programmieraufgabe (Einzelnutzer)' });
  await page.getByRole('button', { name: 'Übungseinheit hinzufügen' }).click();
  await page.getByRole('textbox', { name: 'Titel' }).fill(title);
  await page.getByRole('radio', { name: 'Keine Abgabefrist' }).check();
  await page.getByRole('button', { name: 'Speichern' }).first().click();
  await page.waitForURL(/ass_id=\d+/);
  return new URL(page.url()).searchParams.get('ass_id') ?? '';
}

export async function deleteAssignment(page: Page, refId: string, title: string): Promise<void> {
  await page.goto(await editorUrl(page, refId));
  const rows = page.getByRole('row', { name: new RegExp(title) });
  const count = await rows.count();
  if (count === 0) return;
  for (let i = 0; i < count; i++) {
    await rows.nth(i).getByRole('checkbox').check();
  }
  await page.getByRole('button', { name: 'Löschen' }).first().click();
  await page.getByRole('button', { name: 'Löschen' }).first().click();
  await page.waitForLoadState('networkidle');
}
