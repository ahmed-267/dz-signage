import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { completeOnboarding, registerCustomer } from './helpers/auth';

test.describe('workspace phase 1 flows', () => {
    test('new user registers, verifies, onboards, and reaches dashboard', async ({
        page,
    }) => {
        const { workspaceName } = await registerCustomer(page);

        await expect(page.getByText(workspaceName).first()).toBeVisible();
        await expect(page.getByText('Owner').first()).toBeVisible();
    });

    test('owner can switch between workspaces', async ({ page }) => {
        await registerCustomer(page);

        await page.goto('/app/workspaces/create');
        await page.locator('#name').fill('Second Workspace');
        await page.locator('#industry').selectOption({ index: 2 });
        await page.locator('#country').fill('United Kingdom');
        await page.locator('#timezone').selectOption('Europe/London');
        await page.getByRole('button', { name: /create workspace/i }).click();
        await page.waitForURL(/\/app\/dashboard/);

        await expect(page.getByText('Second Workspace').first()).toBeVisible();

        await page.getByTestId('workspace-switcher').click();
        await page.locator('[data-test^="workspace-option-"]').first().click();
        await expect(page.getByTestId('workspace-switcher')).toBeVisible();
    });

    test('owner invites user and invitee accepts', async ({ page }) => {
        const owner = await registerCustomer(page);
        const inviteeEmail = `invitee-${Date.now()}@example.com`;

        execFileSync(
            'php',
            [
                'artisan',
                'tinker',
                '--execute',
                `
                \\App\\Models\\User::factory()->create([
                    'email' => ${JSON.stringify(inviteeEmail)},
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]);
                `,
            ],
            { cwd: process.cwd(), stdio: 'pipe' },
        );

        await page.goto('/app/team');
        await page.getByTestId('team-invite-button').click();
        await page.getByTestId('team-invite-email').fill(inviteeEmail);
        await page.locator('#invite-role').selectOption('designer');
        await page.getByTestId('team-invite-submit').click();
        await expect(page.getByText(inviteeEmail).first()).toBeVisible();

        const token = execFileSync(
            'php',
            [
                'artisan',
                'tinker',
                '--execute',
                `
                $invitation = \\App\\Models\\WorkspaceInvitation::query()
                    ->where('email', ${JSON.stringify(inviteeEmail)})
                    ->latest('id')
                    ->firstOrFail();
                $plain = \\App\\Models\\WorkspaceInvitation::generatePlainToken();
                $invitation->forceFill([
                    'token_hash' => \\App\\Models\\WorkspaceInvitation::hashToken($plain),
                ])->save();
                echo $plain;
                `,
            ],
            { cwd: process.cwd(), encoding: 'utf8' },
        ).trim();

        await page.context().clearCookies();
        await page.goto('/login');
        await page.getByLabel(/email/i).fill(inviteeEmail);
        await page.locator('#password').fill('password');
        await page.getByTestId('login-button').click();
        await page.waitForURL(/\/(onboarding|app\/dashboard|invitations)/);

        await page.goto(`/invitations/${token}`);
        await page.getByTestId('invitation-accept').click();
        await page.waitForURL(/\/app\/dashboard/);
        await expect(page.getByText(owner.workspaceName).first()).toBeVisible();
        await expect(page.getByText('Designer').first()).toBeVisible();
    });

    test('viewer cannot invite team members', async ({ page }) => {
        const owner = await registerCustomer(page);
        const viewerEmail = `viewer-${Date.now()}@example.com`;

        execFileSync(
            'php',
            [
                'artisan',
                'tinker',
                '--execute',
                `
                $owner = \\App\\Models\\User::where('email', ${JSON.stringify(owner.email)})->firstOrFail();
                $workspaceId = $owner->current_workspace_id;
                $viewer = \\App\\Models\\User::factory()->create([
                    'email' => ${JSON.stringify(viewerEmail)},
                    'password' => 'password',
                ]);
                \\App\\Models\\WorkspaceMember::query()->create([
                    'workspace_id' => $workspaceId,
                    'user_id' => $viewer->id,
                    'role' => 'viewer',
                ]);
                $viewer->forceFill(['current_workspace_id' => $workspaceId])->save();
                `,
            ],
            { cwd: process.cwd(), stdio: 'pipe' },
        );

        await page.context().clearCookies();
        await page.goto('/login');
        await page.getByLabel(/email/i).fill(viewerEmail);
        await page.locator('#password').fill('password');
        await page.getByTestId('login-button').click();
        await page.waitForURL(/\/app\/dashboard/);

        await page.goto('/app/team');
        await expect(page.getByTestId('team-invite-button')).toHaveCount(0);
    });
});

test.describe('workspace mobile smoke', () => {
    test('onboarding form is usable on mobile viewport', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });

        const email = `mobile-${Date.now()}@example.com`;
        await page.goto('/register');
        await page.getByLabel('Name').fill('Mobile User');
        await page.getByLabel('Email address').fill(email);
        await page.locator('#password').fill('password');
        await page.locator('#password_confirmation').fill('password');
        await page.getByRole('button', { name: /create account/i }).click();
        await page.waitForURL(/\/(email\/verify|onboarding|app\/dashboard)/);

        if (page.url().includes('/email/verify')) {
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

        await completeOnboarding(page, 'Mobile Workspace');
        await expect(page.getByText('Mobile Workspace').first()).toBeVisible();
    });
});
