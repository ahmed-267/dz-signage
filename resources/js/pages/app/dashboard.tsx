import { Head } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { dashboard } from '@/routes/app';

type WorkspaceSummary = {
    name: string;
    industry: string;
    role: string | null;
    country: string;
    timezone: string;
};

type Props = {
    workspaceSummary: WorkspaceSummary | null;
};

export default function AppDashboard({ workspaceSummary }: Props) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">
                        Dashboard
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Your DZ Signage workspace overview will live here.
                    </p>
                </div>

                {workspaceSummary ? (
                    <Card className="max-w-xl gap-2 py-4 shadow-none">
                        <CardHeader className="px-4">
                            <CardDescription className="text-xs font-medium tracking-wide uppercase">
                                Current workspace
                            </CardDescription>
                            <CardTitle className="font-display text-xl">
                                {workspaceSummary.name}
                            </CardTitle>
                            <div className="mt-2 flex flex-wrap gap-2">
                                <Badge variant="secondary">
                                    {workspaceSummary.industry}
                                </Badge>
                                {workspaceSummary.role ? (
                                    <Badge variant="info">
                                        {workspaceSummary.role}
                                    </Badge>
                                ) : null}
                            </div>
                            <p className="text-muted-foreground mt-2 text-sm">
                                {workspaceSummary.country} ·{' '}
                                {workspaceSummary.timezone}
                            </p>
                        </CardHeader>
                    </Card>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {[
                        { label: 'Screens', value: '—' },
                        { label: 'Playlists', value: '—' },
                        { label: 'Schedules', value: '—' },
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
                    icon={LayoutDashboard}
                    title="Nothing to show yet"
                    description="Once screens and playlists are connected, activity will appear on this dashboard."
                />
            </div>
        </>
    );
}

AppDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
