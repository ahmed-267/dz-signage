import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
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
import { store } from '@/routes/onboarding';

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

export default function OnboardingWorkspace({ industries, timezones }: Props) {
    const [step, setStep] = useState(1);
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

    const industryLabel =
        industries.find((item) => item.value === data.industry)?.label ??
        data.industry;

    const canContinueStep1 =
        data.name.trim().length > 0 &&
        data.industry.length > 0 &&
        data.country.trim().length > 0 &&
        data.timezone.length > 0;

    function submit() {
        post(store.url(), {
            forceFormData: data.logo !== null,
        });
    }

    return (
        <>
            <Head title="Create your workspace" />
            <div className="bg-background relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_color-mix(in_oklch,var(--primary)_14%,transparent),_transparent_50%)]"
                />
                <Card className="relative z-10 w-full max-w-lg shadow-sm">
                    <CardHeader className="items-center text-center">
                        <div className="bg-primary text-primary-foreground mb-2 flex size-9 items-center justify-center rounded-lg">
                            <AppLogoIcon className="size-5" />
                        </div>
                        <CardTitle className="font-display text-2xl tracking-tight">
                            {step === 1 && 'Set up your organisation'}
                            {step === 2 && 'Add your branding'}
                            {step === 3 && 'Ready to go'}
                        </CardTitle>
                        <CardDescription>
                            {step === 1 &&
                                'Tell us about your workspace so DZ Signage can get you started.'}
                            {step === 2 &&
                                'Optional — you can upload a logo now or skip and add it later.'}
                            {step === 3 &&
                                'Confirm your details, then enter DZ Signage.'}
                        </CardDescription>
                        <p className="text-muted-foreground text-xs">
                            Step {step} of 3
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {step === 1 && (
                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">
                                        Organisation name
                                    </Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(event) =>
                                            setData('name', event.target.value)
                                        }
                                        required
                                        autoFocus
                                        placeholder="Acme Displays"
                                        data-test="onboarding-name"
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
                                            setData(
                                                'industry',
                                                event.target.value,
                                            )
                                        }
                                        data-test="onboarding-industry"
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
                                            setData(
                                                'country',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        placeholder="United Kingdom"
                                        data-test="onboarding-country"
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
                                            setData(
                                                'timezone',
                                                event.target.value,
                                            )
                                        }
                                        data-test="onboarding-timezone"
                                    >
                                        {timezones.map((timezone) => (
                                            <option
                                                key={timezone}
                                                value={timezone}
                                            >
                                                {timezone}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.timezone} />
                                </div>
                                <Button
                                    type="button"
                                    className="w-full"
                                    disabled={!canContinueStep1}
                                    onClick={() => setStep(2)}
                                    data-test="onboarding-continue"
                                >
                                    Continue
                                </Button>
                            </div>
                        )}

                        {step === 2 && (
                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="logo">Logo</Label>
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
                                    {data.logo ? (
                                        <p className="text-muted-foreground text-xs">
                                            Selected: {data.logo.name}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() => {
                                            setData('logo', null);
                                            setStep(3);
                                        }}
                                        data-test="onboarding-skip-logo"
                                    >
                                        Skip
                                    </Button>
                                    <Button
                                        type="button"
                                        className="flex-1"
                                        onClick={() => setStep(3)}
                                        data-test="onboarding-continue"
                                    >
                                        Continue
                                    </Button>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="w-full"
                                    onClick={() => setStep(1)}
                                >
                                    Back
                                </Button>
                            </div>
                        )}

                        {step === 3 && (
                            <div className="space-y-4">
                                <dl className="bg-muted/40 space-y-3 rounded-lg border p-4 text-sm">
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Organisation
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {data.name}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Industry
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {industryLabel}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Country
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {data.country}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Timezone
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {data.timezone}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Logo
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {data.logo
                                                ? data.logo.name
                                                : 'Skipped'}
                                        </dd>
                                    </div>
                                </dl>
                                <Button
                                    type="button"
                                    className="w-full"
                                    disabled={processing}
                                    onClick={submit}
                                    data-test="onboarding-submit"
                                >
                                    {processing ? <Spinner /> : null}
                                    Enter DZ Signage
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="w-full"
                                    onClick={() => setStep(2)}
                                >
                                    Back
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
