import { Head, Link, router } from '@inertiajs/react';
import { Archive, ArrowLeft, Copy, Eye } from 'lucide-react';
import { useState } from 'react';
import { ScheduleForm } from '@/components/schedules/schedule-form';
import {
    formatWindow,
    statusBadgeVariant,
} from '@/components/schedules/schedule-format';
import { SchedulePreviewDialog } from '@/components/schedules/schedule-preview-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { schedules as schedulesIndex } from '@/routes/app';
import scheduleRoutes from '@/routes/app/schedules';
import type { ScheduleEditProps } from '@/types/schedule';

export default function ScheduleEdit({
    schedule,
    can_edit: canEdit,
    ...formProps
}: ScheduleEditProps) {
    const [previewOpen, setPreviewOpen] = useState(false);
    const isArchived = schedule.stored_status === 'archived';

    return (
        <>
            <Head title={schedule.name} />
            <div
                className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="schedule-edit"
                data-status={schedule.status}
            >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-display truncate text-2xl font-semibold tracking-tight">
                                {schedule.name}
                            </h1>
                            <Badge
                                variant={statusBadgeVariant(schedule.status)}
                                data-test="schedule-edit-status"
                            >
                                {schedule.status_label}
                            </Badge>
                            {schedule.is_live ? (
                                <Badge
                                    variant="success"
                                    data-test="schedule-edit-live"
                                >
                                    On now
                                </Badge>
                            ) : null}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            Next run{' '}
                            <span className="font-mono">
                                {formatWindow(schedule.next_window)}
                            </span>
                        </p>
                    </div>
                    <div className="flex shrink-0 flex-wrap gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href={schedulesIndex.url()}>
                                <ArrowLeft className="size-4" />
                                Schedules
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            data-test="schedule-edit-preview"
                            onClick={() => setPreviewOpen(true)}
                        >
                            <Eye className="size-4" />
                            Preview
                        </Button>
                        {canEdit ? (
                            <Button
                                type="button"
                                variant="outline"
                                data-test="schedule-edit-duplicate"
                                onClick={() =>
                                    router.post(
                                        scheduleRoutes.duplicate.url(
                                            schedule.id,
                                        ),
                                    )
                                }
                            >
                                <Copy className="size-4" />
                                Duplicate
                            </Button>
                        ) : null}
                        {canEdit && !isArchived ? (
                            <Button
                                type="button"
                                variant="outline"
                                data-test="schedule-edit-archive"
                                onClick={() =>
                                    router.post(
                                        scheduleRoutes.archive.url(schedule.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Archive className="size-4" />
                                Archive
                            </Button>
                        ) : null}
                    </div>
                </div>

                {isArchived ? (
                    <Alert data-test="schedule-archived-notice">
                        <Archive />
                        <AlertTitle>This schedule is archived</AlertTitle>
                        <AlertDescription>
                            Archived schedules never play and cannot be edited.
                            Duplicate it to make changes.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <ScheduleForm
                    {...formProps}
                    schedule={schedule}
                    canEdit={canEdit && !isArchived}
                />
            </div>

            <SchedulePreviewDialog
                scheduleId={previewOpen ? schedule.id : null}
                scheduleName={schedule.name}
                shortLabels={formProps.config.day_short_labels}
                onClose={() => setPreviewOpen(false)}
            />
        </>
    );
}

ScheduleEdit.layout = {
    breadcrumbs: [
        { title: 'Schedules', href: '/app/schedules' },
        { title: 'Edit', href: '#' },
    ],
};
