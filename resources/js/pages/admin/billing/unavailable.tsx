import { Head, Link } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { EmptyState } from '@/components/ui/empty-state';

type Props = {
    title?: string;
    path?: string;
    billing_unavailable?: boolean;
    message?: string;
};

export default function AdminBillingUnavailable({
    title = 'Billing',
    path,
    message,
}: Props) {
    return (
        <>
            <Head title={title} />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-billing-unavailable"
            >
                <AdminPageHeader
                    title={title}
                    description="Billing surfaces stay truthful until a payment provider is configured."
                />
                <EmptyState
                    icon={CreditCard}
                    title="Billing has not been configured yet."
                    description={
                        message ??
                        'Subscription management will become available when platform billing is enabled.'
                    }
                    className="min-h-[40vh] flex-1"
                />
            </div>
        </>
    );
}

AdminBillingUnavailable.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: props.title ?? 'Billing',
            href: props.path ?? '/admin/subscriptions',
        },
    ],
});
