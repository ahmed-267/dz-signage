import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

type Props = {
    reason: string;
};

const reasonCopy: Record<string, { title: string; description: string }> = {
    invalid: {
        title: 'Invitation not found',
        description:
            'This invitation link is invalid or no longer exists. Ask your workspace admin to send a new one.',
    },
    accepted: {
        title: 'Invitation already accepted',
        description:
            'This invitation has already been used. Sign in to open the workspace if you already joined.',
    },
    revoked: {
        title: 'Invitation cancelled',
        description:
            'This invitation was cancelled by a workspace admin. Request a new invite if you still need access.',
    },
    expired: {
        title: 'Invitation expired',
        description:
            'This invitation has expired. Ask your workspace admin to resend it.',
    },
};

export default function InvitationInvalid({ reason }: Props) {
    const copy = reasonCopy[reason] ?? {
        title: 'Invitation unavailable',
        description:
            'This invitation cannot be used. Contact your workspace admin for help.',
    };

    return (
        <>
            <Head title={copy.title} />
            <div className="bg-background relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_color-mix(in_oklch,var(--primary)_14%,transparent),_transparent_50%)]"
                />
                <Card className="relative z-10 w-full max-w-md shadow-sm">
                    <CardHeader className="items-center text-center">
                        <div className="bg-primary text-primary-foreground mb-2 flex size-9 items-center justify-center rounded-lg">
                            <AppLogoIcon className="size-5" />
                        </div>
                        <CardTitle className="font-display text-2xl tracking-tight">
                            {copy.title}
                        </CardTitle>
                        <CardDescription>{copy.description}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button className="w-full" asChild>
                            <Link href={home()}>Back to DZ Signage</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
