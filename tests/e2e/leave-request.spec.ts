import { expect, test } from '@playwright/test';
import { ACCOUNTS, futureWeek, requestLeave, signIn } from './helpers';

/**
 * The main flow through the real app: an employee asks for leave, their
 * manager approves it, HR accepts it, and the employee is notified.
 */
test('leave request: employee → manager → HR → employee notified', async ({ browser }) => {
    const period = futureWeek(3);

    // 1. The employee files a request.
    const employee = await signIn(browser, 'employee');
    await requestLeave(employee, period, 'Vacances en famille (E2E)');
    const myRow = employee.getByRole('row').filter({ hasText: 'Vacances en famille (E2E)' });
    await expect(myRow).toContainText('En attente');

    // 2. Their manager validates it on "Mon équipe".
    const manager = await signIn(browser, 'manager');
    await manager.getByRole('link', { name: 'Mon équipe' }).click();
    const toValidate = manager.getByRole('listitem').filter({ hasText: 'Vacances en famille (E2E)' });
    await expect(toValidate).toContainText(ACCOUNTS.employee.name);
    await expect(toValidate).toContainText('Congé annuel');
    await toValidate.getByRole('button', { name: 'Valider' }).click();
    await expect(manager.getByRole('listitem').filter({ hasText: 'Vacances en famille (E2E)' })).toContainText('Validée par le responsable');

    // 3. HR accepts it.
    const hr = await signIn(browser, 'hr');
    await hr.getByRole('link', { name: 'Gérer les demandes' }).click();
    const hrRow = hr.getByRole('row').filter({ hasText: 'Vacances en famille (E2E)' });
    await expect(hrRow).toContainText('Validée par le responsable');
    await hrRow.getByRole('button', { name: 'Modifier' }).click();
    const edit = hr.getByRole('dialog', { name: 'Modifier la demande de congé' });
    await edit.getByLabel('État').selectOption('acceptée');
    await edit.getByRole('button', { name: 'Modifier demande' }).click();
    await expect(edit.getByText('Demande mise à jour avec succès')).toBeVisible();
    await expect(hrRow).toContainText('Acceptée');

    // 4. The employee sees the decision and the notification.
    await employee.reload();
    await expect(myRow).toContainText('Acceptée');
    await employee.getByRole('button', { name: 'Notifications' }).click();
    await expect(employee.getByRole('menuitem').filter({ hasText: 'Votre demande de congé a été acceptée.' })).toBeVisible();
});

test('a manager refusal is final and its reason reaches the employee', async ({ browser }) => {
    const period = futureWeek(6);

    const employee = await signIn(browser, 'employee');
    await requestLeave(employee, period, 'Pont (E2E)');

    const manager = await signIn(browser, 'manager');
    await manager.goto('/equipe');
    manager.once('dialog', (dialog) => dialog.accept('Clôture des comptes'));
    await manager.getByRole('listitem').filter({ hasText: 'Pont (E2E)' }).getByRole('button', { name: 'Refuser' }).click();
    await expect(manager.getByRole('listitem').filter({ hasText: 'Pont (E2E)' })).toContainText('Motif du refus : Clôture des comptes');

    await employee.reload();
    const row = employee.getByRole('row').filter({ hasText: 'Pont (E2E)' });
    await expect(row).toContainText('Refusée');
    await expect(row).toContainText('Motif du responsable : Clôture des comptes');
});
