import { Head } from '@inertiajs/react';
import { EmptyState } from '@/components/ui/empty-state';

type Props = {
    title: string;
    path?: string;
};

export default function AdminComingSoon({ title }: Props) {
    return (
        <>
            <Head title={title} />
            <div className="flex h-full flex-1 flex-col p-4 md:p-6">
                <EmptyState
                    title={title}
                    description="Platform tooling for this section will be built in a later phase. Routing and navigation are in place."
                    className="min-h-[50vh] flex-1"
                />
            </div>
        </>
    );
}

AdminComingSoon.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: props.title,
            href: props.path ?? '/admin/dashboard',
        },
    ],
});
