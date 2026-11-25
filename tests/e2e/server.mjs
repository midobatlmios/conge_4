// Server for the end-to-end tests, started by playwright.config.ts.
// 1. Recreates the throwaway database (database/e2e.sqlite) with the E2eSeeder accounts.
// 2. Serves the app with APP_ENV=e2e, so Laravel loads .env.e2e (never the real .env database).
import { execFileSync, spawn } from 'node:child_process';
import { rmSync, writeFileSync } from 'node:fs';

const port = process.argv[2] ?? '8001';
const env = { ...process.env, APP_ENV: 'e2e' };
const database = 'database/e2e.sqlite';

rmSync(database, { force: true });
writeFileSync(database, '');

execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--seeder=E2eSeeder', '--force', '--no-interaction'], {
    env,
    stdio: 'inherit',
});

const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`, '--no-reload'], { env, stdio: 'inherit' });

// Stop PHP's server together with this script.
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        server.kill();
        process.exit(0);
    });
}
server.on('exit', (code) => process.exit(code ?? 0));
