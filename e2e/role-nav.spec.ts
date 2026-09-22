import { execFileSync } from 'node:child_process';
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

function ensureRoleUser(email: string, role: string): void {
    const script = `
$user = \\App\\Models\\User::query()->firstOrCreate(
    ['email' => ${JSON.stringify(email)}],
    [
        'name' => ${JSON.stringify(role)},
        'password' => 'password',
        'email_verified_at' => now(),
    ],
);
$user->forceFill([
    'password' => 'password',
    'email_verified_at' => now(),
])->save();
$owner = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
$workspace = $owner->currentWorkspace;
\\App\\Models\\WorkspaceMember::query()->updateOrCreate(
    ['workspace_id' => $workspace->id, 'user_id' => $user->id],
    ['role' => \\App\\Enums\\WorkspaceRole::from(${JSON.stringify(role)})],
);
$user->forceFill(['current_workspace_id' => $workspace->id])->save();
echo 'ok';
`;

    execFileSync('php', ['artisan', 'tinker', '--execute', script], {
        cwd: process.cwd(),
        encoding: 'utf8',
    });
}

test.describe('role-based customer sidebar', () => {
    test('designer does not see publishing or screens nav', async ({
        page,
    }) => {
        ensureRoleUser('designer@dz.local', 'designer');
        await login(page, 'designer@dz.local');
        await page.goto('/app/dashboard');

        const sidebar = page.getByTestId('customer-sidebar');
        await expect(sidebar).toBeVisible();
        await expect(sidebar.getByText('Screens')).toBeVisible();
        await expect(sidebar.getByText('Brand Kit')).toBeVisible();
        await expect(page.getByTestId('create-design-cta')).toHaveCount(0);
        await expect(sidebar.getByText('Publishing')).toHaveCount(0);
        await expect(sidebar.getByText('Playlists')).toHaveCount(0);
        await expect(sidebar.getByText('Schedules')).toHaveCount(0);
        await expect(sidebar.getByText('Paired TVs')).toHaveCount(0);
        await expect(sidebar.getByText('Locations')).toHaveCount(0);
    });

    test('content manager sees publishing and screens', async ({ page }) => {
        ensureRoleUser('content@dz.local', 'content_manager');
        await login(page, 'content@dz.local');
        await page.goto('/app/dashboard');

        const sidebar = page.getByTestId('customer-sidebar');
        await expect(sidebar.getByText('Publishing')).toBeVisible();
        await expect(sidebar.getByText('Playlists')).toBeVisible();
        await expect(sidebar.getByText('Schedules')).toBeVisible();
        await expect(sidebar.getByText('Paired TVs')).toBeVisible();
        await expect(sidebar.getByText('Locations')).toHaveCount(0);
    });

    test('location manager sees screens and locations only in display', async ({
        page,
    }) => {
        ensureRoleUser('location@dz.local', 'location_manager');
        await login(page, 'location@dz.local');
        await page.goto('/app/dashboard');

        const sidebar = page.getByTestId('customer-sidebar');
        await expect(sidebar.getByText('Paired TVs')).toBeVisible();
        await expect(sidebar.getByText('Locations')).toBeVisible();
        await expect(sidebar.getByText('Publishing')).toHaveCount(0);
        await expect(sidebar.getByText('Playlists')).toHaveCount(0);
        await expect(page.getByTestId('create-design-cta')).toHaveCount(0);
    });
});

test.describe('admin portal naming', () => {
    test('admin topbar says Admin Portal not Super Admin Portal', async ({
        page,
    }) => {
        await login(page, 'platform@dz.local');
        await page.goto('/admin/dashboard');
        await expect(page.getByTestId('admin-topbar')).toContainText(
            'Admin Portal',
        );
        await expect(page.getByTestId('admin-topbar')).not.toContainText(
            'Super Admin Portal',
        );
        await expect(page.getByTestId('admin-sidebar')).toContainText(
            'Platform Admin',
        );
    });
});
