import { useForm, usePage } from '@inertiajs/react';
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
import { update } from '@/routes/app/workspace_settings';

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

export type WorkspacePanelProps = {
    workspaceSettings: WorkspaceSettings;
    industries: Option[];
    timezones: string[];
    countries: Option[];
};

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

export function WorkspacePanel({
    workspaceSettings,
    industries,
    timezones,
    countries,
}: WorkspacePanelProps) {
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
        name: workspaceSettings.name,
        industry: workspaceSettings.industry,
        country: workspaceSettings.country,
        timezone: workspaceSettings.timezone,
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
        <div
            className="flex flex-col gap-6"
            data-test="workspace-settings-panel"
        >
            <Card className="max-w-xl shadow-none">
                <CardHeader>
                    <CardTitle className="font-display text-lg">
                        Organisation
                    </CardTitle>
                    <CardDescription>
                        {canManage
                            ? 'Update the name, industry, location, and logo for this business.'
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
                            <select
                                id="country"
                                className={selectClassName}
                                value={data.country}
                                onChange={(event) =>
                                    setData('country', event.target.value)
                                }
                                required
                                disabled={!canManage}
                                data-test="workspace-settings-country"
                            >
                                {countries.map((country) => (
                                    <option
                                        key={country.value}
                                        value={country.value}
                                    >
                                        {country.label}
                                    </option>
                                ))}
                            </select>
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
                            {workspaceSettings.logo_url ? (
                                <img
                                    src={workspaceSettings.logo_url}
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
                    <CardTitle className="text-base">Delete business</CardTitle>
                    <CardDescription>
                        Business deletion is handled by RMSignage support.
                        Contact support if you need to close a business.
                    </CardDescription>
                </CardHeader>
            </Card>
        </div>
    );
}
