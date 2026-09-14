import { Head, Link, useForm } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { accept, show as invitationShow } from '@/routes/invitations';
import { login, register } from '@/routes';

type Props = {
    token: string;
    invitation: {
        email: string;
        role: string;
        workspace: string | null;
        expires_at: string;
    };
    authenticated: boolean;
    email_matches: boolean;
};

export default function InvitationShow({
    token,
    invitation,
    authenticated,
    email_matches,
}: Props) {
    const { post, processing } = useForm({});
    const redirectUrl = invitationShow.url(token);

    function acceptInvitation() {
        post(accept.url(token));
    }

    return (
        <>
            <Head title="Workspace invitation" />
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
                            You&apos;re invited
                        </CardTitle>
                        <CardDescription>
                            Join{' '}
                            <span className="text-foreground font-medium">
                                {invitation.workspace ?? 'a workspace'}
                            </span>{' '}
                            as {invitation.role}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <dl className="bg-muted/40 space-y-2 rounded-lg border p-4 text-sm">
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">Email</dt>
                                <dd className="font-medium">
                                    {invitation.email}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">Role</dt>
                                <dd className="font-medium">
                                    {invitation.role}
                                </dd>
                            </div>
                        </dl>

                        {authenticated && email_matches ? (
                            <Button
                                type="button"
                                className="w-full"
                                disabled={processing}
                                onClick={acceptInvitation}
                                data-test="invitation-accept"
                            >
                                {processing ? <Spinner /> : null}
                                Accept invitation
                            </Button>
                        ) : authenticated && !email_matches ? (
                            <p className="text-muted-foreground text-center text-sm text-pretty">
                                You&apos;re signed in with a different email.
                                Sign in as{' '}
                                <span className="text-foreground font-medium">
                                    {invitation.email}
                                </span>{' '}
                                to accept this invitation.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                <p className="text-muted-foreground text-center text-sm">
                                    Sign in or create an account with{' '}
                                    <span className="text-foreground font-medium">
                                        {invitation.email}
                                    </span>{' '}
                                    to continue.
                                </p>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Button className="flex-1" asChild>
                                        <Link
                                            href={login.url({
                                                query: {
                                                    redirect: redirectUrl,
                                                },
                                            })}
                                        >
                                            Log in
                                        </Link>
                                    </Button>
                                    <Button
                                        className="flex-1"
                                        variant="outline"
                                        asChild
                                    >
                                        <Link
                                            href={register.url({
                                                query: {
                                                    redirect: redirectUrl,
                                                },
                                            })}
                                        >
                                            Register
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
