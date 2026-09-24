import { Head } from '@inertiajs/react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ADMIN_OPERATIONS_TABS } from '@/components/admin/admin-section-header';
import { StatusBadge } from '@/components/admin/status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { system_health as systemHealthIndex } from '@/routes/admin';

type HealthCheck = {
    key?: string;
    name?: string;
    label?: string;
    status?: string;
    status_label?: string;
    message?: string | null;
    checked_at?: string | null;
};

type Props = {
    checks?: HealthCheck[];
    overall_status?: string | null;
    overall_status_label?: string | null;
};

function formatWhen(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

export default function AdminSystemHealth({
    checks = [],
    overall_status,
    overall_status_label,
}: Props) {
    return (
        <>
            <Head title="System Health" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-system-health"
            >
                <AdminPageHeader
                    title="Operations"
                    description="Monitor TVs, deployments and platform health."
                    badge={null}
                    tabs={ADMIN_OPERATIONS_TABS}
                    activeTab="system-health"
                />

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Checks
                        </CardTitle>
                        <CardDescription>
                            {checks.length === 0
                                ? 'No health checks reported yet.'
                                : `${checks.length} checks`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {checks.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Health check results will appear when the
                                backend probes are wired.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Check</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Detail</TableHead>
                                        <TableHead>Checked</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {checks.map((check, index) => {
                                        const label =
                                            check.label ??
                                            check.name ??
                                            check.key ??
                                            `Check ${index + 1}`;
                                        const statusLabel =
                                            check.status_label ??
                                            check.status ??
                                            'Unknown';

                                        return (
                                            <TableRow
                                                key={
                                                    check.key ??
                                                    check.name ??
                                                    index
                                                }
                                            >
                                                <TableCell className="font-medium">
                                                    {label}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        label={statusLabel}
                                                        tone={check.status}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-muted-foreground max-w-md text-sm">
                                                    {check.message ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">
                                                    {formatWhen(
                                                        check.checked_at,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminSystemHealth.layout = {
    breadcrumbs: [
        {
            title: 'System Health',
            href: systemHealthIndex(),
        },
    ],
};
