import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';

/**
 * Mark a registered user as email-verified (local e2e only).
 * Production flows use the Fortify verification email link.
 */
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

/**
 * Register a fresh customer account and land on /app/dashboard.
 */
export async function registerCustomer(page: Page): Promise<{
    email: string;
    password: string;
}> {
    const email = `e2e-${Date.now()}-${Math.floor(Math.random() * 10_000)}@example.com`;
    const password = 'password';

    await page.goto('/register');
    await page.getByLabel('Name').fill('E2E User');
    await page.getByLabel('Email address').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('#password_confirmation').fill(password);
    await page.getByRole('button', { name: /create account/i }).click();

    // With MustVerifyEmail, Fortify redirects home then middleware sends
    // unverified users to the verification notice.
    await page.waitForURL(/\/(app\/dashboard|email\/verify)/);

    if (page.url().includes('/email/verify')) {
        markEmailVerified(email);
        await page.goto('/app/dashboard');
    }

    await page.waitForURL(/\/app\/dashboard/);

    return { email, password };
}
