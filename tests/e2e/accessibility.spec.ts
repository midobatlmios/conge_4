import AxeBuilder from '@axe-core/playwright';
import { expect, Page, test } from '@playwright/test';
import { ACCOUNTS, signIn } from './helpers';

/**
 * Accessibility (WCAG 2.1 A and AA) of every page, checked with axe-core in a
 * real browser, as each kind of user sees it. Fails on any violation, with
 * the list of what to fix.
 */
async function expectAccessible(page: Page, url: string): Promise<void> {
    await page.goto(url);
    await page.waitForLoadState('networkidle');

    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const problems = results.violations.map(
        (violation) =>
            `${violation.id} (${violation.impact}): ${violation.help}\n` +
            violation.nodes
                .slice(0, 5)
                .map((node) => {
                    // For contrast, the measured ratio ("…contrast of 3.2 (foreground #…)…").
                    const detail = violation.id === 'color-contrast' ? `  → ${(node.failureSummary ?? '').split('\n').pop()}` : '';
                    return `    ${node.target.join(' ')}  ${node.html.slice(0, 140)}${detail}`;
                })
                .join('\n'),
    );

    // Soft: one run lists the problems of every page, not only the first one.
    expect.soft(problems, `${url}\n${problems.join('\n')}`).toEqual([]);
}

test('the sign-in page is accessible', async ({ page }) => {
    await expectAccessible(page, '/login');
    await expectAccessible(page, '/forgot-password');
});

const pages: Record<keyof typeof ACCOUNTS, string[]> = {
    employee: [
        '/dashboard',
        '/calendrier',
        '/organigramme',
        '/protection-des-donnees',
        '/settings/profile',
        '/settings/personal-data',
        '/settings/documents',
        '/settings/attestations',
        '/settings/calendar',
    ],
    manager: ['/equipe'],
    hr: [
        '/demandes',
        '/admin/overview',
        '/admin/users',
        '/admin/indicators',
        '/admin/attestations',
        '/admin/payroll',
        '/admin/departments',
        '/admin/holidays',
        '/admin/company',
        '/admin/import',
    ],
    admin: ['/admin/privacy', '/admin/privacy/log'],
};

for (const [account, urls] of Object.entries(pages) as [keyof typeof ACCOUNTS, string[]][]) {
    test(`pages seen by the ${account} account are accessible`, async ({ browser }) => {
        const page = await signIn(browser, account);
        for (const url of urls) {
            await expectAccessible(page, url);
        }
    });
}

test('the employee page (file, contracts, documents) is accessible', async ({ browser }) => {
    const page = await signIn(browser, 'hr');
    await page.goto('/admin/users');
    await page.getByRole('link', { name: ACCOUNTS.employee.name }).click();
    await expectAccessible(page, page.url());
});

// The same pages in dark mode (the app follows the system setting by default).
test.describe('dark mode', () => {
    test.use({ colorScheme: 'dark' });

    for (const account of ['employee', 'hr'] as const) {
        test(`pages seen by the ${account} account are accessible in dark mode`, async ({ browser }) => {
            const page = await signIn(browser, account);
            for (const url of pages[account]) {
                await expectAccessible(page, url);
            }
        });
    }
});
