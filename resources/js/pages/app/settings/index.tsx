import { Head, Link } from '@inertiajs/react';
import { BillingPanel } from '@/components/settings/billing-panel';
import {
    GeneralPanel,
    type GeneralPanelProps,
} from '@/components/settings/general-panel';
import {
    SecurityPanel,
    type SecurityPanelProps,
} from '@/components/settings/security-panel';
import {
    WorkspacePanel,
    type WorkspacePanelProps,
} from '@/components/settings/workspace-panel';
import { cn } from '@/lib/utils';
import { settings as settingsIndex } from '@/routes/app';
import type { BillingIndexProps } from '@/types/billing';

export type SettingsTab = 'general' | 'workspace' | 'billing' | 'security';

type AvailableTabs = {
    general: boolean;
    workspace: boolean;
    billing: boolean;
    security: boolean;
};

type SettingsHubProps = {
    tab: SettingsTab;
    availableTabs: AvailableTabs;
} & Partial<GeneralPanelProps> &
    Partial<WorkspacePanelProps> &
    Partial<BillingIndexProps> &
    Partial<SecurityPanelProps>;

const TAB_LABELS: Record<SettingsTab, string> = {
    general: 'General',
    workspace: 'Business',
    billing: 'Billing',
    security: 'Security',
};

function tabHref(tab: SettingsTab): string {
    if (tab === 'general') {
        return settingsIndex.url();
    }

    return `${settingsIndex.url()}/${tab}`;
}

export default function SettingsHub({
    tab,
    availableTabs,
    ...panelProps
}: SettingsHubProps) {
    const tabs = (Object.keys(TAB_LABELS) as SettingsTab[]).filter(
        (key) => availableTabs[key],
    );

    return (
        <>
            <Head title="Settings" />
            <div
                data-test="app-settings"
                className="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">
                        Settings
                    </h1>
                    <p className="text-muted-foreground mt-0.5 text-sm">
                        Manage your profile, workspace, billing, and security.
                    </p>
                </div>

                <nav
                    className="border-border flex flex-wrap gap-1 border-b"
                    aria-label="Settings sections"
                    data-test="settings-tabs"
                >
                    {tabs.map((key) => (
                        <Link
                            key={key}
                            href={tabHref(key)}
                            prefetch
                            data-test={`settings-tab-${key}`}
                            className={cn(
                                'border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                                tab === key
                                    ? 'border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground border-transparent',
                            )}
                        >
                            {TAB_LABELS[key]}
                        </Link>
                    ))}
                </nav>

                {tab === 'general' ? (
                    <GeneralPanel
                        mustVerifyEmail={Boolean(panelProps.mustVerifyEmail)}
                        status={panelProps.status}
                    />
                ) : null}

                {tab === 'workspace' &&
                panelProps.workspaceSettings &&
                panelProps.industries &&
                panelProps.timezones &&
                panelProps.countries ? (
                    <WorkspacePanel
                        workspaceSettings={panelProps.workspaceSettings}
                        industries={panelProps.industries}
                        timezones={panelProps.timezones}
                        countries={panelProps.countries}
                    />
                ) : null}

                {tab === 'billing' &&
                panelProps.summary &&
                panelProps.plans &&
                panelProps.comparison &&
                panelProps.licence_limits &&
                panelProps.invoices &&
                panelProps.permissions ? (
                    <BillingPanel
                        summary={panelProps.summary}
                        plans={panelProps.plans}
                        comparison={panelProps.comparison}
                        prices={panelProps.prices ?? []}
                        licence_limits={panelProps.licence_limits}
                        unavailable_message={
                            panelProps.unavailable_message ?? null
                        }
                        stripe_configured={panelProps.stripe_configured}
                        invoices={panelProps.invoices}
                        permissions={panelProps.permissions}
                        checkout={panelProps.checkout ?? null}
                    />
                ) : null}

                {tab === 'security' && panelProps.passwordRules != null ? (
                    <SecurityPanel
                        passwordRules={panelProps.passwordRules}
                        canManageTwoFactor={Boolean(
                            panelProps.canManageTwoFactor,
                        )}
                        canManagePasskeys={Boolean(
                            panelProps.canManagePasskeys,
                        )}
                        passkeys={panelProps.passkeys ?? []}
                        requiresConfirmation={panelProps.requiresConfirmation}
                        twoFactorEnabled={panelProps.twoFactorEnabled}
                    />
                ) : null}
            </div>
        </>
    );
}

SettingsHub.layout = {
    breadcrumbs: [{ title: 'Settings', href: settingsIndex.url() }],
};
