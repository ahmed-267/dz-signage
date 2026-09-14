import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';

function markEmailVerified(email: string): void {
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `\\App\\Models\\User::where('email', ${JSON.stringify(email)})->update(['email_verified_at' => now()]);`,
        ],
        { cwd: process.cwd(), stdio: 'pipe' },
    );
}

export async function completeOnboarding(
    page: Page,
    workspaceName = 'E2E Workspace',
): Promise<void> {
    if (!page.url().includes('/onboarding')) {
        await page.goto('/onboarding');
    }

    await page.getByTestId('onboarding-name').fill(workspaceName);
    await page.getByTestId('onboarding-industry').selectOption({ index: 1 });
    await page.getByTestId('onboarding-country').fill('United Kingdom');
    await page.getByTestId('onboarding-timezone').selectOption('Europe/London');
    await page.getByTestId('onboarding-continue').click();
    await page.getByTestId('onboarding-skip-logo').click();
    await page.getByTestId('onboarding-submit').click();
    await page.waitForURL(/\/app\/dashboard/);
}

/**
 * Register → verify → onboard → dashboard.
 */
export async function registerCustomer(page: Page): Promise<{
    email: string;
    password: string;
    workspaceName: string;
}> {
    const email = `e2e-${Date.now()}-${Math.floor(Math.random() * 10_000)}@example.com`;
    const password = 'password';
    const workspaceName = `E2E Workspace ${Date.now()}`;

    await page.goto('/register');
    await page.getByLabel('Name').fill('E2E User');
    await page.getByLabel('Email address').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('#password_confirmation').fill(password);
    await page.getByRole('button', { name: /create account/i }).click();
    await page.waitForURL(/\/(email\/verify|onboarding|app\/dashboard)/);

    if (page.url().includes('/email/verify')) {
        markEmailVerified(email);
    }

    await completeOnboarding(page, workspaceName);

    return { email, password, workspaceName };
}
