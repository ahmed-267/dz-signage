import { Head, Link } from '@inertiajs/react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { publishing_jobs as publishingJobsIndex } from '@/routes/admin';

type Props = {
    deployment: {
        id: number;
        workspace_name?: string | null;
        screen_name?: string | null;
        content_name?: string | null;
        content_type_label?: string | null;
        status_label?: string | null;
        sync_label?: string | null;
        deployed_by_name?: string | null;
        deployed_at?: string | null;
    };
};

export default function AdminPublishingJobShow({ deployment }: Props) {
    return (
        <>
            <Head title={`Deployment #${deployment.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <AdminPageHeader
                    title={`Deployment #${deployment.id}`}
                    description={deployment.content_name ?? undefined}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={publishingJobsIndex()}>Back</Link>
                        </Button>
                    }
                />
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Details
                        </CardTitle>
                        <CardDescription>
                            Read-only publishing job.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <div>Workspace: {deployment.workspace_name ?? '—'}</div>
                        <div>Screen: {deployment.screen_name ?? '—'}</div>
                        <div>Content: {deployment.content_name ?? '—'}</div>
                        <div>Type: {deployment.content_type_label ?? '—'}</div>
                        <div>Status: {deployment.status_label ?? '—'}</div>
                        <div>Sync: {deployment.sync_label ?? '—'}</div>
                        <div>
                            Deployed by: {deployment.deployed_by_name ?? '—'}
                        </div>
                        <div>Deployed at: {deployment.deployed_at ?? '—'}</div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminPublishingJobShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Publishing Jobs', href: publishingJobsIndex() },
        {
            title: `#${props.deployment.id}`,
            href: `/admin/publishing-jobs/${props.deployment.id}`,
        },
    ],
});
