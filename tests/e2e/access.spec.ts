import { expect, test } from '@playwright/test';
import { signIn } from './helpers';

test('a wrong password is refused with a message in French', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Adresse e-mail').fill('employe@e2e.test');
    await page.getByLabel('Mot de passe').fill('pas-le-bon');
    await page.getByRole('button', { name: 'Se connecter' }).click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText('Ces identifiants ne correspondent à aucun compte.')).toBeVisible();
});

test('employees do not see or reach the admin pages', async ({ browser }) => {
    const employee = await signIn(browser, 'employee');

    await expect(employee.getByRole('link', { name: 'Gérer les employés' })).toHaveCount(0);
    const response = await employee.goto('/admin/users');
    expect(response?.status()).toBe(403);
});

test('a mariage request warns that a certificate is still missing', async ({ browser }) => {
    const employee = await signIn(browser, 'employee');

    await employee.getByRole('button', { name: 'Nouvelle demande' }).click();
    const dialog = employee.getByRole('dialog', { name: 'Ajouter une demande de congé' });
    await dialog.getByRole('combobox').filter({ hasText: 'Congé annuel' }).click();
    await employee.getByRole('option', { name: /Congé de Mariage/ }).click();
    await expect(dialog.getByLabel('Justificatif')).toBeVisible();
    await dialog.getByRole('button', { name: 'Soumettre la demande' }).click();
    await expect(dialog).toBeHidden();

    const row = employee.getByRole('row').filter({ hasText: 'Congé de Mariage' });
    await expect(row.getByRole('button', { name: /Justificatifs manquant/ })).toBeVisible();
});
