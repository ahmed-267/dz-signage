import { Head } from '@inertiajs/react';
import { EmptyState } from '@/components/ui/empty-state';

type Props = {
    title: string;
    path?: string;
};

export default function ComingSoon({ title }: Props) {
    return (
        <>
            <Head title={title} />
            <div className="flex h-full flex-1 flex-col p-4 md:p-6">
                <EmptyState
                    title={title}
                    description="This area is reserved for a future DZ Signage feature. Navigation and routing are ready for later phases."
                    className="min-h-[50vh] flex-1"
                />
            </div>
        </>
    );
}

ComingSoon.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: props.title,
            href: props.path ?? '/app/dashboard',
        },
    ],
});
