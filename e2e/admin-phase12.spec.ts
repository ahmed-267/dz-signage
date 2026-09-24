import { expect, test, type Page } from '@playwright/test';

async function login(
    page: Page,
    email: string,
    password = 'password',
): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(email);
    await page.locator('#password').fill(password);
    await page.getByTestId('login-button').click();
    await page.waitForURL(/\/(app|admin)\//, { timeout: 20_000 });
}

test.describe('phase 12 admin portal', () => {
    test.describe.configure({ mode: 'serial' });

    test('Flow A — Admin shell', async ({ page }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/dashboard');
        await expect(page.getByTestId('admin-sidebar')).toBeVisible();
        await expect(page.getByTestId('admin-overview')).toBeVisible();
        await expect(page.getByText('RMSignage Admin').first()).toBeVisible();
        await expect(
            page.getByTestId('admin-sidebar').getByText('Support'),
        ).toBeVisible();
        await expect(
            page.getByTestId('admin-sidebar').getByText('Operations'),
        ).toBeVisible();
        await expect(
            page.getByTestId('admin-sidebar').getByText('Customers'),
        ).toBeVisible();
        await expect(page.getByTestId('customer-sidebar')).toHaveCount(0);
    });

    test('Flow B — Workspace list and detail', async ({ page }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/workspaces');
        await expect(page.getByTestId('admin-workspaces')).toBeVisible();
        await page.getByTestId('admin-workspaces-search').fill('North & Bean');
        await page.getByTestId('admin-workspaces-search').press('Enter');
        const link = page
            .getByTestId('admin-workspaces')
            .getByRole('link', { name: /North & Bean/i })
            .first();
        await expect(link).toBeVisible({ timeout: 10_000 });
        await Promise.all([
            page.waitForURL(/\/admin\/workspaces\/\d+/, { timeout: 15_000 }),
            link.click(),
        ]);
        await expect(page.getByTestId('admin-workspace-detail')).toBeVisible({
            timeout: 15_000,
        });
    });

    test('Flow C — User detail', async ({ page }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/users');
        await expect(page.getByTestId('admin-users')).toBeVisible();
        await page.getByTestId('admin-users-search').fill('owner@dz.local');
        await page.getByTestId('admin-users-search').press('Enter');
        const link = page
            .locator('a')
            .filter({ hasText: /owner@dz\.local|Local Owner/i })
            .first();
        await expect(link).toBeVisible({ timeout: 10_000 });
        await link.click();
        await expect(page.getByTestId('admin-user-detail')).toBeVisible();
    });

    test('Flow D/E — Screens and Screen Health', async ({ page }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/screens');
        await expect(page.getByTestId('admin-screens-search')).toBeVisible({
            timeout: 15_000,
        });
        await page.goto('/admin/screen-health');
        await expect(page.getByTestId('admin-screen-health')).toBeVisible();
    });

    test('Flow F — Publishing Jobs', async ({ page }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/publishing-jobs');
        await expect(page.getByTestId('admin-publishing-jobs')).toBeVisible();
    });

    test('Flow G — Support request customer → admin', async ({ page }) => {
        test.setTimeout(90_000);
        await login(page, 'owner@dz.local');
        await page.goto('/app/help');
        await expect(page.getByTestId('app-help-form')).toBeVisible();
        const subject = `E2E Support ${Date.now()}`;
        await page.getByTestId('app-help-subject').fill(subject);
        await page.getByTestId('app-help-category').selectOption({ index: 1 });
        await page
            .getByTestId('app-help-message')
            .fill('E2E support request body for Phase 12.');
        await page.getByTestId('app-help-submit').click();
        await expect(page.getByTestId('app-help-success')).toBeVisible({
            timeout: 10_000,
        });

        await login(page, 'admin@dz.local');
        await page.goto('/admin/support');
        await expect(page.getByTestId('admin-support')).toBeVisible();
        await page.getByTestId('admin-support-search').fill(subject);
        const detailLink = page.getByRole('link', { name: subject });
        await expect(detailLink).toBeVisible({ timeout: 15_000 });
        const href = await detailLink.getAttribute('href');
        expect(href).toBeTruthy();
        await page.goto(href!);
        await expect(page.getByTestId('admin-support-detail')).toBeVisible({
            timeout: 15_000,
        });
        await page.locator('#status').selectOption('in_progress');
        await page.getByRole('button', { name: /save changes/i }).click();
        await expect(page.getByTestId('admin-support-detail')).toContainText(
            /in progress/i,
            { timeout: 10_000 },
        );
    });

    test('Flow H — Feature flag toggle + audit', async ({ page }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/feature-flags');
        await expect(page.getByTestId('admin-feature-flags')).toBeVisible();
        const flag = page.getByTestId('admin-feature-flag-widgets_enabled');
        await expect(flag).toBeVisible();
        await flag.getByRole('switch').click();
        await page.getByRole('button', { name: /^confirm$/i }).click();
        await page.goto('/admin/audit-log');
        await expect(page.getByTestId('admin-audit-log')).toBeVisible();
        await expect(page.getByTestId('admin-tab-audit-log')).toBeVisible();
        await expect(
            page
                .getByTestId('admin-section-tabs')
                .getByText('Support Requests'),
        ).toHaveCount(0);
        await expect(
            page.locator('table').getByText('feature_flag.updated').first(),
        ).toBeVisible({ timeout: 10_000 });
        // Restore flag
        await page.goto('/admin/feature-flags');
        const flagAgain = page.getByTestId(
            'admin-feature-flag-widgets_enabled',
        );
        await flagAgain.getByRole('switch').click();
        await page.getByRole('button', { name: /^confirm$/i }).click();
    });

    test('Flow I — Platform Admin denied flag write', async ({ page }) => {
        await login(page, 'platform@dz.local');
        await page.goto('/admin/dashboard');
        await expect(page.getByTestId('admin-overview')).toBeVisible();
        await page.goto('/admin/feature-flags');
        await expect(page.getByTestId('admin-feature-flags')).toBeVisible();
        const flag = page.getByTestId('admin-feature-flag-widgets_enabled');
        await expect(flag.getByRole('switch')).toBeDisabled();
    });

    test('Flow J — Workspace user denied admin', async ({ page }) => {
        await login(page, 'owner@dz.local');
        const response = await page.goto('/admin/dashboard');
        expect(response?.status()).toBe(403);
    });

    test('Flow K — mobile admin nav', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'admin@dz.local');
        await page.goto('/admin/dashboard');
        await expect(page.getByTestId('admin-overview')).toBeVisible();
        await page.goto('/admin/system-health');
        await expect(page.getByTestId('admin-system-health')).toBeVisible();
        await page.goto('/admin/subscriptions');
        // Plan catalog remains usable without Stripe; only mutations are gated.
        await expect(
            page
                .getByTestId('admin-subscriptions')
                .or(page.getByTestId('admin-billing-unavailable'))
                .first(),
        ).toBeVisible();
    });
});
