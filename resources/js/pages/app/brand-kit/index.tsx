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
import { update as updateBrandKit } from '@/routes/app/brand_kit';
import type { BrandKitForm, BrandKitMediaOption } from '@/types/brand-kit';

type FontOption = {
    value: string;
    label: string;
};

type Props = {
    brandKit: BrandKitForm;
    mediaOptions: BrandKitMediaOption[];
    fonts: FontOption[];
    permissions: {
        can_manage: boolean;
    };
};

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

const COLOR_FIELDS = [
    { key: 'primary_color', label: 'Primary' },
    { key: 'secondary_color', label: 'Secondary' },
    { key: 'accent_color', label: 'Accent' },
    { key: 'background_color', label: 'Background' },
    { key: 'text_color', label: 'Text' },
] as const;

function toColorInput(hex: string): string {
    const normalized = hex.trim();
    if (/^#[0-9A-Fa-f]{6}$/.test(normalized)) {
        return normalized;
    }
    if (/^#[0-9A-Fa-f]{3}$/.test(normalized)) {
        const h = normalized.slice(1);
        return `#${h[0]}${h[0]}${h[1]}${h[1]}${h[2]}${h[2]}`;
    }
    return '#000000';
}

export default function BrandKitPage({
    brandKit,
    mediaOptions,
    fonts,
    permissions,
}: Props) {
    const canManage = permissions.can_manage;

    const { data, setData, put, processing, errors } = useForm({
        name: brandKit.name ?? '',
        tagline: brandKit.tagline ?? '',
        primary_color: brandKit.primary_color,
        secondary_color: brandKit.secondary_color,
        accent_color: brandKit.accent_color,
        background_color: brandKit.background_color,
        text_color: brandKit.text_color,
        heading_font: brandKit.heading_font,
        body_font: brandKit.body_font,
        logo_media_asset_id: brandKit.logo_media_asset_id,
        secondary_logo_media_asset_id: brandKit.secondary_logo_media_asset_id,
    });

    const logoPreview =
        mediaOptions.find((m) => m.id === data.logo_media_asset_id)
            ?.preview_url ?? brandKit.logo_url;
    const secondaryPreview =
        mediaOptions.find((m) => m.id === data.secondary_logo_media_asset_id)
            ?.preview_url ?? brandKit.secondary_logo_url;

    function submit(event: FormEvent) {
        event.preventDefault();
        if (!canManage) {
            return;
        }

        put(updateBrandKit.url({ brandKit: brandKit.id }), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Brand Kit" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Brand Kit
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Define colours, fonts, and logos used across Screen
                            Designs and AI generation.
                        </p>
                    </div>
                    {canManage ? (
                        <Button
                            type="submit"
                            form="brand-kit-form"
                            disabled={processing}
                            data-test="brand-kit-save"
                        >
                            {processing ? <Spinner /> : null}
                            Save Brand Kit
                        </Button>
                    ) : null}
                </div>

                <form
                    id="brand-kit-form"
                    onSubmit={submit}
                    className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
                >
                    <div className="space-y-6">
                        <Card className="shadow-none">
                            <CardHeader>
                                <CardTitle className="font-display text-lg">
                                    Brand identity
                                </CardTitle>
                                <CardDescription>
                                    {canManage
                                        ? 'Name and tagline for this business brand.'
                                        : 'You can view the Brand Kit, but only owners, admins, and designers can edit it.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="brand-name">Name</Label>
                                    <Input
                                        id="brand-name"
                                        data-test="brand-kit-name"
                                        value={data.name}
                                        disabled={!canManage}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="brand-tagline">
                                        Tagline
                                    </Label>
                                    <Input
                                        id="brand-tagline"
                                        data-test="brand-kit-tagline"
                                        value={data.tagline}
                                        disabled={!canManage}
                                        onChange={(e) =>
                                            setData('tagline', e.target.value)
                                        }
                                    />
                                    <InputError message={errors.tagline} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="shadow-none">
                            <CardHeader>
                                <CardTitle className="font-display text-lg">
                                    Logos
                                </CardTitle>
                                <CardDescription>
                                    Choose image or logo Media assets from this
                                    business.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="brand-logo">
                                        Primary logo
                                    </Label>
                                    <select
                                        id="brand-logo"
                                        data-test="brand-kit-logo"
                                        className={selectClassName}
                                        disabled={!canManage}
                                        value={data.logo_media_asset_id ?? ''}
                                        onChange={(e) =>
                                            setData(
                                                'logo_media_asset_id',
                                                e.target.value
                                                    ? Number(e.target.value)
                                                    : null,
                                            )
                                        }
                                    >
                                        <option value="">None</option>
                                        {mediaOptions.map((asset) => (
                                            <option
                                                key={asset.id}
                                                value={asset.id}
                                            >
                                                {asset.name} ({asset.type})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.logo_media_asset_id}
                                    />
                                    {logoPreview ? (
                                        <img
                                            src={logoPreview}
                                            alt=""
                                            className="border-border mt-1 h-16 w-auto rounded-md border object-contain p-1"
                                        />
                                    ) : (
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            No logo selected
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="brand-secondary-logo">
                                        Secondary logo
                                    </Label>
                                    <select
                                        id="brand-secondary-logo"
                                        data-test="brand-kit-secondary-logo"
                                        className={selectClassName}
                                        disabled={!canManage}
                                        value={
                                            data.secondary_logo_media_asset_id ??
                                            ''
                                        }
                                        onChange={(e) =>
                                            setData(
                                                'secondary_logo_media_asset_id',
                                                e.target.value
                                                    ? Number(e.target.value)
                                                    : null,
                                            )
                                        }
                                    >
                                        <option value="">None</option>
                                        {mediaOptions.map((asset) => (
                                            <option
                                                key={asset.id}
                                                value={asset.id}
                                            >
                                                {asset.name} ({asset.type})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={
                                            errors.secondary_logo_media_asset_id
                                        }
                                    />
                                    {secondaryPreview ? (
                                        <img
                                            src={secondaryPreview}
                                            alt=""
                                            className="border-border mt-1 h-16 w-auto rounded-md border object-contain p-1"
                                        />
                                    ) : (
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            No logo selected
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="shadow-none">
                            <CardHeader>
                                <CardTitle className="font-display text-lg">
                                    Colours
                                </CardTitle>
                                <CardDescription>
                                    Hex colours (#RGB or #RRGGBB) for canvases
                                    and AI prompts.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                {COLOR_FIELDS.map((field) => (
                                    <div key={field.key} className="grid gap-2">
                                        <Label htmlFor={field.key}>
                                            {field.label}
                                        </Label>
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="color"
                                                aria-label={`${field.label} colour picker`}
                                                className="border-border size-9 cursor-pointer rounded-md border bg-transparent p-0.5 disabled:cursor-not-allowed disabled:opacity-50"
                                                disabled={!canManage}
                                                value={toColorInput(
                                                    data[field.key],
                                                )}
                                                onChange={(e) =>
                                                    setData(
                                                        field.key,
                                                        e.target.value.toUpperCase(),
                                                    )
                                                }
                                            />
                                            <Input
                                                id={field.key}
                                                data-test={`brand-kit-${field.key}`}
                                                value={data[field.key]}
                                                disabled={!canManage}
                                                onChange={(e) =>
                                                    setData(
                                                        field.key,
                                                        e.target.value,
                                                    )
                                                }
                                                className="font-mono uppercase"
                                            />
                                        </div>
                                        <div className="flex gap-1.5">
                                            {[
                                                data.primary_color,
                                                data.secondary_color,
                                                data.accent_color,
                                                data.background_color,
                                                data.text_color,
                                            ].map((swatch, index) => (
                                                <button
                                                    key={`${field.key}-swatch-${index}`}
                                                    type="button"
                                                    disabled={!canManage}
                                                    title={swatch}
                                                    className="border-border size-6 rounded-md border"
                                                    style={{
                                                        backgroundColor:
                                                            toColorInput(
                                                                swatch,
                                                            ),
                                                    }}
                                                    onClick={() =>
                                                        setData(
                                                            field.key,
                                                            swatch,
                                                        )
                                                    }
                                                />
                                            ))}
                                        </div>
                                        <InputError
                                            message={errors[field.key]}
                                        />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card className="shadow-none">
                            <CardHeader>
                                <CardTitle className="font-display text-lg">
                                    Typography
                                </CardTitle>
                                <CardDescription>
                                    Web-safe stacks that match the Screen
                                    editor.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="heading_font">
                                        Heading font
                                    </Label>
                                    <select
                                        id="heading_font"
                                        data-test="brand-kit-heading-font"
                                        className={selectClassName}
                                        disabled={!canManage}
                                        value={data.heading_font}
                                        onChange={(e) =>
                                            setData(
                                                'heading_font',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {fonts.map((font) => (
                                            <option
                                                key={font.value}
                                                value={font.value}
                                            >
                                                {font.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.heading_font} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="body_font">Body font</Label>
                                    <select
                                        id="body_font"
                                        data-test="brand-kit-body-font"
                                        className={selectClassName}
                                        disabled={!canManage}
                                        value={data.body_font}
                                        onChange={(e) =>
                                            setData('body_font', e.target.value)
                                        }
                                    >
                                        {fonts.map((font) => (
                                            <option
                                                key={font.value}
                                                value={font.value}
                                            >
                                                {font.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.body_font} />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="h-fit shadow-none lg:sticky lg:top-6">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Live preview
                            </CardTitle>
                            <CardDescription>
                                How your brand may look on a canvas.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                className="overflow-hidden rounded-xl border"
                                style={{
                                    backgroundColor: toColorInput(
                                        data.background_color,
                                    ),
                                    borderColor: toColorInput(
                                        data.secondary_color,
                                    ),
                                }}
                                data-test="brand-kit-preview"
                            >
                                <div
                                    className="flex items-center justify-between px-4 py-3"
                                    style={{
                                        backgroundColor: toColorInput(
                                            data.primary_color,
                                        ),
                                    }}
                                >
                                    {logoPreview ? (
                                        <img
                                            src={logoPreview}
                                            alt=""
                                            className="h-8 w-auto object-contain"
                                        />
                                    ) : (
                                        <span
                                            className="text-sm font-medium opacity-70"
                                            style={{
                                                color: toColorInput(
                                                    data.background_color,
                                                ),
                                                fontFamily: data.heading_font,
                                            }}
                                        >
                                            No logo selected
                                        </span>
                                    )}
                                    <span
                                        className="rounded-md px-2 py-1 text-[10px] font-medium tracking-wide uppercase"
                                        style={{
                                            backgroundColor: toColorInput(
                                                data.accent_color,
                                            ),
                                            color: toColorInput(
                                                data.background_color,
                                            ),
                                        }}
                                    >
                                        Accent
                                    </span>
                                </div>
                                <div className="space-y-3 p-4">
                                    <p
                                        className="text-xl font-semibold tracking-tight"
                                        style={{
                                            color: toColorInput(
                                                data.text_color,
                                            ),
                                            fontFamily: data.heading_font,
                                        }}
                                    >
                                        {data.name || 'Your brand name'}
                                    </p>
                                    <p
                                        className="text-sm"
                                        style={{
                                            color: toColorInput(
                                                data.secondary_color,
                                            ),
                                            fontFamily: data.body_font,
                                        }}
                                    >
                                        {data.tagline ||
                                            'Your tagline appears here for preview.'}
                                    </p>
                                    <div className="flex gap-2 pt-1">
                                        {[
                                            data.primary_color,
                                            data.secondary_color,
                                            data.accent_color,
                                            data.text_color,
                                        ].map((swatch) => (
                                            <span
                                                key={swatch}
                                                className="size-7 rounded-full border border-black/10"
                                                style={{
                                                    backgroundColor:
                                                        toColorInput(swatch),
                                                }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}
