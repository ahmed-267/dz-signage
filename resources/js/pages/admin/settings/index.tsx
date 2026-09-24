import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ADMIN_SETTINGS_TABS } from '@/components/admin/admin-section-header';
import { Badge } from '@/components/ui/badge';
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
import { settings as settingsIndex } from '@/routes/admin';
import { update as updateSettings } from '@/routes/admin/settings';

type SettingField = {
    key: string;
    label?: string | null;
    description?: string | null;
    type?:
        | 'string'
        | 'text'
        | 'number'
        | 'int'
        | 'boolean'
        | 'bool'
        | 'textarea';
    value?: string | number | boolean | null;
    effective?: string | number | boolean | null;
    source?: string | null;
};

type Props = {
    settings?: SettingField[];
    can_manage?: boolean;
    permissions?: {
        can_manage_platform_settings?: boolean;
        is_super_admin?: boolean;
    };
};

export default function AdminSettings({
    settings = [],
    can_manage,
    permissions,
}: Props) {
    const allowManage =
        can_manage ?? permissions?.can_manage_platform_settings ?? false;

    const { flash } = usePage().props as {
        flash?: { success?: string };
    };

    const form = useForm({
        key: settings[0]?.key ?? '',
        value: settings[0]?.value ?? settings[0]?.effective ?? '',
    });

    useEffect(() => {
        if (!form.data.key && settings[0]?.key) {
            form.setData({
                key: settings[0].key,
                value: settings[0].value ?? settings[0].effective ?? '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- sync initial key once settings arrive
    }, [settings]);

    const selected =
        settings.find((setting) => setting.key === form.data.key) ??
        settings[0];

    function selectSetting(key: string) {
        const next = settings.find((setting) => setting.key === key);
        form.setData({
            key,
            value: next?.value ?? next?.effective ?? '',
        });
        form.clearErrors();
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        if (!allowManage) {
            return;
        }

        form.patch(updateSettings.url(), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Platform Settings" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-settings"
            >
                <AdminPageHeader
                    title="Settings"
                    description="Platform settings, feature flags and audit log."
                    badge={null}
                    tabs={ADMIN_SETTINGS_TABS}
                    activeTab="platform"
                />

                {flash?.success ? (
                    <p className="text-success text-sm font-medium">
                        {flash.success}
                    </p>
                ) : null}

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Settings
                        </CardTitle>
                        <CardDescription>
                            {settings.length === 0
                                ? 'No allowlisted settings are available yet.'
                                : allowManage
                                  ? 'Select a setting and save one value at a time.'
                                  : 'Read-only for your role.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {settings.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Platform settings will appear here when the
                                backend exposes allowlisted keys.
                            </p>
                        ) : (
                            <form className="space-y-6" onSubmit={submit}>
                                <div className="space-y-2">
                                    <Label htmlFor="setting_key">Setting</Label>
                                    <select
                                        id="setting_key"
                                        className="border-input flex h-9 w-full max-w-md rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none"
                                        value={form.data.key}
                                        onChange={(event) =>
                                            selectSetting(event.target.value)
                                        }
                                    >
                                        {settings.map((setting) => (
                                            <option
                                                key={setting.key}
                                                value={setting.key}
                                            >
                                                {setting.label ?? setting.key}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {selected ? (
                                    <div className="space-y-3 rounded-lg border p-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">
                                                {selected.label ?? selected.key}
                                            </p>
                                            {selected.source ? (
                                                <Badge variant="secondary">
                                                    {selected.source}
                                                </Badge>
                                            ) : null}
                                        </div>
                                        {selected.description ? (
                                            <p className="text-muted-foreground text-sm">
                                                {selected.description}
                                            </p>
                                        ) : null}
                                        <p className="text-muted-foreground text-xs">
                                            Effective:{' '}
                                            {String(
                                                selected.effective ??
                                                    selected.value ??
                                                    '—',
                                            )}
                                        </p>
                                        <div className="space-y-2">
                                            <Label htmlFor="setting_value">
                                                Value
                                            </Label>
                                            <Input
                                                id="setting_value"
                                                type={
                                                    selected.type === 'int' ||
                                                    selected.type === 'number'
                                                        ? 'number'
                                                        : 'text'
                                                }
                                                value={String(
                                                    form.data.value ?? '',
                                                )}
                                                disabled={!allowManage}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'value',
                                                        selected.type ===
                                                            'int' ||
                                                            selected.type ===
                                                                'number'
                                                            ? event.target
                                                                  .valueAsNumber
                                                            : event.target
                                                                  .value,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                ) : null}

                                {allowManage ? (
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing || !form.data.key
                                        }
                                    >
                                        Save setting
                                    </Button>
                                ) : null}
                            </form>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminSettings.layout = {
    breadcrumbs: [
        {
            title: 'Platform Settings',
            href: settingsIndex(),
        },
    ],
};
