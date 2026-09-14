import { Head, useForm } from '@inertiajs/react';
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
import { create, store } from '@/routes/app/workspaces';

type Option = {
    value: string;
    label: string;
};

type Props = {
    industries: Option[];
    timezones: string[];
};

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

export default function CreateWorkspace({ industries, timezones }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        industry: string;
        country: string;
        timezone: string;
        logo: File | null;
    }>({
        name: '',
        industry: industries[0]?.value ?? '',
        country: '',
        timezone:
            Intl.DateTimeFormat().resolvedOptions().timeZone ||
            timezones[0] ||
            'UTC',
        logo: null,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        post(store.url(), {
            forceFormData: data.logo !== null,
        });
    }

    return (
        <>
            <Head title="Create workspace" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">
                        Create workspace
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Add another organisation workspace to your account.
                    </p>
                </div>

                <Card className="max-w-xl shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Organisation details
                        </CardTitle>
                        <CardDescription>
                            These settings can be updated later from workspace
                            settings.
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
                                    autoFocus
                                    placeholder="Acme Displays"
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
                                    placeholder="United Kingdom"
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
                                <Label htmlFor="logo">Logo (optional)</Label>
                                <Input
                                    id="logo"
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) =>
                                        setData(
                                            'logo',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={errors.logo} />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing ? <Spinner /> : null}
                                Create workspace
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CreateWorkspace.layout = {
    breadcrumbs: [
        {
            title: 'Create workspace',
            href: create(),
        },
    ],
};
