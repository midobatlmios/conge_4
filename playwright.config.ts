import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests: a real browser drives the real app (`npm run test:e2e`).
 *
 * Before the run, the server below rebuilds a throwaway SQLite database from
 * scratch (.env.e2e) and serves the app on port 8001, so the development
 * database is never touched. The frontend must be built (`npm run build`).
 */
const PORT = 8001;

export default defineConfig({
    testDir: './tests/e2e',
    // The scenarios share one database and follow a request from step to step.
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: `http://127.0.0.1:${PORT}`,
        locale: 'fr-FR',
        timezoneId: 'Africa/Casablanca',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: `node tests/e2e/server.mjs ${PORT}`,
        url: `http://127.0.0.1:${PORT}/login`,
        reuseExistingServer: false,
        timeout: 120_000,
        stdout: 'ignore',
        stderr: 'pipe',
    },
});
