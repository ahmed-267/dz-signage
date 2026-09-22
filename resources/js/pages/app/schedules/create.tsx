import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ScheduleForm } from '@/components/schedules/schedule-form';
import { Button } from '@/components/ui/button';
import { ProductLabels } from '@/lib/product-labels';
import { schedules as schedulesIndex } from '@/routes/app';
import type { ScheduleCreateProps } from '@/types/schedule';

export default function ScheduleCreate(props: ScheduleCreateProps) {
    return (
        <>
            <Head title="Create Schedule" />
            <div
                className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="schedule-create"
            >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Create Schedule
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            Play a published playlist on selected{' '}
                            {ProductLabels.displayPlural} at the right time.
                            Save the draft, then activate it.
                        </p>
                    </div>
                    <Button type="button" variant="outline" asChild>
                        <Link href={schedulesIndex.url()}>
                            <ArrowLeft className="size-4" />
                            Schedules
                        </Link>
                    </Button>
                </div>

                <ScheduleForm {...props} canEdit />
            </div>
        </>
    );
}

ScheduleCreate.layout = {
    breadcrumbs: [
        { title: 'Schedules', href: '/app/schedules' },
        { title: 'Create', href: '/app/schedules/create' },
    ],
};
