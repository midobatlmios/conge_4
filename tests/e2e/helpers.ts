import { Browser, expect, Page } from '@playwright/test';

/** Accounts created by database/seeders/E2eSeeder.php (password "password"). */
export const ACCOUNTS = {
    admin: { email: 'admin@e2e.test', name: 'Amal Admin' },
    hr: { email: 'rh@e2e.test', name: 'Rachid RH' },
    manager: { email: 'manager@e2e.test', name: 'Meryem Responsable' },
    employee: { email: 'employe@e2e.test', name: 'Elias Employé' },
} as const;

/** Opens a fresh browser session signed in as one of the test accounts. */
export async function signIn(browser: Browser, account: keyof typeof ACCOUNTS): Promise<Page> {
    const page = await (await browser.newContext()).newPage();
    await page.goto('/login');
    await page.getByLabel('Adresse e-mail').fill(ACCOUNTS[account].email);
    await page.getByLabel('Mot de passe').fill('password');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    return page;
}

const iso = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

/**
 * A Monday-to-Tuesday period at least `weeksAhead` weeks away, as YYYY-MM-DD,
 * so it is in the future and has two working days (holidays aside).
 */
export function futureWeek(weeksAhead: number): { start: string; end: string; startLabel: string } {
    const monday = new Date();
    monday.setDate(monday.getDate() + weeksAhead * 7 + ((8 - monday.getDay()) % 7 || 7));
    const tuesday = new Date(monday);
    tuesday.setDate(monday.getDate() + 1);

    return { start: iso(monday), end: iso(tuesday), startLabel: monday.toLocaleDateString('fr-FR') };
}

/** Files an annual leave request from the employee's dashboard. */
export async function requestLeave(page: Page, period: { start: string; end: string }, comment: string): Promise<void> {
    await page.getByRole('button', { name: 'Nouvelle demande' }).click();
    const dialog = page.getByRole('dialog', { name: 'Ajouter une demande de congé' });
    await dialog.getByLabel('Date de début').fill(period.start);
    await dialog.getByLabel('Date de fin').fill(period.end);
    await dialog.getByLabel('Commentaire').fill(comment);
    await dialog.getByRole('button', { name: 'Soumettre la demande' }).click();
    await expect(dialog.getByText('Demande ajoutée avec succès')).toBeVisible();
    await expect(dialog).toBeHidden();
}
