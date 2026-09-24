import { expect, test, type Page } from '@playwright/test';

async function login(
    page: Page,
    email: string,
    password = 'password',
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(email);
    await page.locator('#password').fill(password);
    await page.getByTestId('login-button').click();
    await page.waitForURL(/\/(app|admin)\//, { timeout: 20_000 });
}

test.describe('admin vs customer shells', () => {
    test.describe.configure({ mode: 'serial' });

    test('admin portal uses dedicated sidebar without customer nav', async ({
        page,
    }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/dashboard');

        await expect(page.getByTestId('admin-sidebar')).toBeVisible();
        await expect(page.getByTestId('admin-overview')).toBeVisible();
        await expect(page.getByTestId('admin-topbar-back')).toBeVisible();
        await expect(page.getByText('RMSignage Admin').first()).toBeVisible();
        await expect(page.getByTestId('admin-topbar')).toContainText(
            'Admin Portal',
        );

        await expect(
            page.getByTestId('admin-sidebar').getByText('Customers'),
        ).toBeVisible();
        await expect(
            page.getByTestId('admin-sidebar').getByText('Content'),
        ).toBeVisible();
        await expect(
            page.getByTestId('admin-sidebar').getByText('Settings'),
        ).toBeVisible();

        // Must not look like the customer product sidebar
        await expect(page.getByTestId('customer-sidebar')).toHaveCount(0);
        await expect(
            page.getByTestId('admin-sidebar').getByText('Brand Kit'),
        ).toHaveCount(0);
        await expect(
            page.getByTestId('admin-sidebar').getByText('Screens'),
        ).toHaveCount(0);
        await expect(
            page.getByTestId('admin-sidebar').getByText('Playlists'),
        ).toHaveCount(0);
    });

    test('customer app uses customer sidebar without platform ops nav', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/dashboard');

        await expect(page.getByTestId('customer-sidebar')).toBeVisible();
        await expect(page.getByTestId('create-design-cta')).toHaveCount(0);
        await expect(page.getByTestId('nav-help')).toBeVisible();
        await expect(
            page.getByTestId('customer-sidebar').getByText('Screens'),
        ).toBeVisible();

        await expect(
            page.getByTestId('customer-sidebar').getByText('Templates'),
        ).toBeVisible();
        await expect(
            page.getByTestId('customer-sidebar').getByText('Media'),
        ).toBeVisible();

        await expect(page.getByTestId('admin-sidebar')).toHaveCount(0);
        await expect(
            page.getByTestId('customer-sidebar').getByText('System Health'),
        ).toHaveCount(0);
        await expect(
            page.getByTestId('customer-sidebar').getByText('Feature Flags'),
        ).toHaveCount(0);
        await expect(
            page.getByTestId('customer-sidebar').getByText('Audit Log'),
        ).toHaveCount(0);
        await expect(page.getByTestId('nav-admin-portal')).toHaveCount(0);
    });

    test('platform staff sees Admin Portal link in customer app', async ({
        page,
    }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/app/dashboard');

        await expect(page.getByTestId('nav-admin-portal')).toBeVisible();
        await page.getByTestId('nav-admin-portal').click();
        await page.waitForURL(/\/admin/);
        await expect(page.getByTestId('admin-sidebar')).toBeVisible();
    });
});
