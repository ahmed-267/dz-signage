import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { edit, update } from '@/routes/app/workspace_settings';
import { edit as editProfile } from '@/routes/profile';

type Option = {
    value: string;
    label: string;
};

type WorkspaceSettings = {
    id: number;
    name: string;
    industry: string;
    country: string;
    timezone: string;
    logo_url: string | null;
};

type Props = {
    workspace: WorkspaceSettings;
    industries: Option[];
    timezones: string[];
};

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

export default function WorkspaceSettingsPage({
    workspace,
    industries,
    timezones,
}: Props) {
    const { workspace: workspaceContext } = usePage().props;
    const canManage =
        workspaceContext?.permissions?.can_manage_workspace ?? false;

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        industry: string;
        country: string;
        timezone: string;
        logo: File | null;
    }>({
        name: workspace.name,
        industry: workspace.industry,
        country: workspace.country,
        timezone: workspace.timezone,
        logo: null,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (!canManage) {
            return;
        }

        post(update.url(), {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Workspace settings" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Workspace settings
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Manage organisation details for this workspace.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={editProfile()}>Account profile</Link>
                    </Button>
                </div>

                <Card className="max-w-xl shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Organisation
                        </CardTitle>
                        <CardDescription>
                            {canManage
                                ? 'Update the name, industry, location, and logo for this workspace.'
                                : 'You can view these settings, but only owners and admins can edit them.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Organisation name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    required
                                    disabled={!canManage}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="industry">Industry</Label>
                                <select
                                    id="industry"
                                    className={selectClassName}
                                    value={data.industry}
                                    onChange={(event) =>
                                        setData('industry', event.target.value)
                                    }
                                    disabled={!canManage}
                                >
                                    {industries.map((industry) => (
                                        <option
                                            key={industry.value}
                                            value={industry.value}
                                        >
                                            {industry.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.industry} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="country">Country</Label>
                                <Input
                                    id="country"
                                    value={data.country}
                                    onChange={(event) =>
                                        setData('country', event.target.value)
                                    }
                                    required
                                    disabled={!canManage}
                                />
                                <InputError message={errors.country} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="timezone">Timezone</Label>
                                <select
                                    id="timezone"
                                    className={selectClassName}
                                    value={data.timezone}
                                    onChange={(event) =>
                                        setData('timezone', event.target.value)
                                    }
                                    disabled={!canManage}
                                >
                                    {timezones.map((timezone) => (
                                        <option key={timezone} value={timezone}>
                                            {timezone}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.timezone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="logo">Logo</Label>
                                {workspace.logo_url ? (
                                    <img
                                        src={workspace.logo_url}
                                        alt=""
                                        className="mb-2 size-16 rounded-md border object-cover"
                                    />
                                ) : null}
                                <Input
                                    id="logo"
                                    type="file"
                                    accept="image/*"
                                    disabled={!canManage}
                                    onChange={(event) =>
                                        setData(
                                            'logo',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={errors.logo} />
                            </div>
                            {canManage ? (
                                <Button type="submit" disabled={processing}>
                                    {processing ? <Spinner /> : null}
                                    Save changes
                                </Button>
                            ) : null}
                        </form>
                    </CardContent>
                </Card>

                <Card className="border-dashed shadow-none">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Delete workspace
                        </CardTitle>
                        <CardDescription>
                            Workspace deletion is not available yet. Contact
                            support if you need to close a workspace.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </>
    );
}

WorkspaceSettingsPage.layout = {
    breadcrumbs: [
        {
            title: 'Workspace settings',
            href: edit(),
        },
    ],
};
