import { defineConfig, devices } from '@playwright/test';

/**
 * DZ Signage — Playwright foundation config.
 * Smoke/e2e tests against the local Laravel app (built assets).
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: 'list',
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8001',
        trace: 'on-first-retry',
        testIdAttribute: 'data-test',
    },
    projects: [
        {
            name: 'desktop-chrome',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'mobile-chrome',
            use: { ...devices['Pixel 5'] },
            testMatch: /surfaces\.spec\.ts/,
        },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8001',
        url: 'http://127.0.0.1:8001',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
    },
});
