/**
 * Playwright global teardown — strip E2E-prefixed fixtures from the local DB
 * so owner@dz.local is not left polluted after the suite.
 */
import { execFileSync } from 'node:child_process';

export default async function globalTeardown(): Promise<void> {
    try {
        execFileSync('php', ['artisan', 'dz:cleanup-e2e'], {
            cwd: process.cwd(),
            encoding: 'utf8',
            stdio: 'inherit',
        });
    } catch (error) {
        console.warn('dz:cleanup-e2e teardown failed:', error);
    }
}
