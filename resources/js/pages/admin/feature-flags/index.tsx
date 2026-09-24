import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ADMIN_SETTINGS_TABS } from '@/components/admin/admin-section-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { feature_flags as featureFlagsIndex } from '@/routes/admin';
import { update as updateFeatureFlag } from '@/routes/admin/feature_flags';

type FeatureFlag = {
    id?: number;
    key: string;
    name?: string | null;
    description?: string | null;
    enabled?: boolean;
};

type Props = {
    flags?: FeatureFlag[];
    can_manage?: boolean;
    permissions?: {
        can_manage_feature_flags?: boolean;
        is_super_admin?: boolean;
    };
};

function FlagSwitch({
    enabled,
    disabled,
    onToggle,
    label,
}: {
    enabled: boolean;
    disabled?: boolean;
    onToggle: () => void;
    label: string;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={enabled}
            aria-label={label}
            disabled={disabled}
            onClick={onToggle}
            className={cn(
                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border transition-colors',
                enabled ? 'border-primary bg-primary' : 'border-input bg-muted',
                disabled && 'cursor-not-allowed opacity-50',
            )}
        >
            <span
                className={cn(
                    'bg-background pointer-events-none block size-5 rounded-full shadow-sm transition-transform',
                    enabled ? 'translate-x-5' : 'translate-x-0.5',
                )}
            />
        </button>
    );
}

export default function AdminFeatureFlags({
    flags = [],
    can_manage,
    permissions,
}: Props) {
    const allowManage =
        can_manage ?? permissions?.can_manage_feature_flags ?? false;

    const [pending, setPending] = useState<FeatureFlag | null>(null);
    const [submitting, setSubmitting] = useState(false);

    function confirmToggle() {
        if (!pending?.id) {
            return;
        }

        setSubmitting(true);
        router.patch(
            updateFeatureFlag.url(pending.id),
            { enabled: !pending.enabled },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSubmitting(false);
                    setPending(null);
                },
            },
        );
    }

    return (
        <>
            <Head title="Feature Flags" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-feature-flags"
            >
                <AdminPageHeader
                    title="Settings"
                    description="Platform settings, feature flags and audit log."
                    badge={null}
                    tabs={ADMIN_SETTINGS_TABS}
                    activeTab="feature-flags"
                />

                {!allowManage ? (
                    <p className="text-muted-foreground text-sm">
                        You can view feature flags, but only Super Admins can
                        change them.
                    </p>
                ) : null}

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Flags
                        </CardTitle>
                        <CardDescription>
                            {flags.length === 0
                                ? 'No feature flags configured yet.'
                                : `${flags.length} flags`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {flags.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Feature flags will appear here when the backend
                                registers them.
                            </p>
                        ) : (
                            flags.map((flag) => {
                                const enabled = Boolean(flag.enabled);

                                return (
                                    <div
                                        key={flag.key}
                                        className="flex flex-wrap items-center justify-between gap-4 rounded-lg border p-4"
                                        data-test={`admin-feature-flag-${flag.key}`}
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium">
                                                    {flag.name ?? flag.key}
                                                </p>
                                                <Badge variant="secondary">
                                                    {flag.key}
                                                </Badge>
                                                <Badge
                                                    variant={
                                                        enabled
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                >
                                                    {enabled
                                                        ? 'Enabled'
                                                        : 'Disabled'}
                                                </Badge>
                                            </div>
                                            {flag.description ? (
                                                <p className="text-muted-foreground text-sm">
                                                    {flag.description}
                                                </p>
                                            ) : null}
                                        </div>
                                        <FlagSwitch
                                            enabled={enabled}
                                            disabled={!allowManage || !flag.id}
                                            label={`Toggle ${flag.name ?? flag.key}`}
                                            onToggle={() => setPending(flag)}
                                        />
                                    </div>
                                );
                            })
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={pending != null}
                onOpenChange={(open) => {
                    if (!open && !submitting) {
                        setPending(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Confirm flag change</DialogTitle>
                        <DialogDescription>
                            {pending
                                ? `${pending.enabled ? 'Disable' : 'Enable'} “${pending.name ?? pending.key}”?`
                                : null}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={submitting}
                            onClick={() => setPending(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={submitting}
                            onClick={confirmToggle}
                        >
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminFeatureFlags.layout = {
    breadcrumbs: [
        {
            title: 'Feature Flags',
            href: featureFlagsIndex(),
        },
    ],
};
