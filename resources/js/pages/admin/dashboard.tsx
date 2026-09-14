import { Head } from '@inertiajs/react';
import { Shield } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { dashboard } from '@/routes/admin';

export default function AdminDashboard() {
    return (
        <>
            <Head title="Admin Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <div>
                        <div className="mb-1 flex items-center gap-2">
                            <Badge variant="info">Super Admin</Badge>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Platform overview
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Operational metrics for workspaces, screens, and
                            billing will appear here in later phases.
                        </p>
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {[
                        { label: 'Workspaces', value: '—' },
                        { label: 'Screens online', value: '—' },
                        { label: 'Active trials', value: '—' },
                    ].map((stat) => (
                        <Card
                            key={stat.label}
                            className="gap-2 py-4 shadow-none"
                        >
                            <CardHeader className="px-4">
                                <CardDescription className="text-xs font-medium tracking-wide uppercase">
                                    {stat.label}
                                </CardDescription>
                                <CardTitle className="text-2xl">
                                    {stat.value}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>
                <EmptyState
                    icon={Shield}
                    title="Platform console ready"
                    description="Customer, billing, and operations tools will plug into this shell in later phases."
                />
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
