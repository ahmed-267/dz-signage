import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { LocationDetail, LocationFormProps } from '@/types/location';

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

type LocationFormFieldsProps = LocationFormProps & {
    location?: LocationDetail;
    submitUrl: string;
    method?: 'post' | 'patch';
    submitLabel: string;
};

export function LocationForm({
    location,
    defaults,
    timezones,
    manager_options: managerOptions,
    can_assign_managers: canAssignManagers,
    submitUrl,
    method = 'post',
    submitLabel,
}: LocationFormFieldsProps) {
    const form = useForm({
        name: location?.name ?? '',
        address_line1: location?.address_line1 ?? '',
        address_line2: location?.address_line2 ?? '',
        city: location?.city ?? '',
        region: location?.region ?? '',
        postcode: location?.postcode ?? '',
        country: location?.country ?? defaults.country,
        timezone: location?.timezone ?? defaults.timezone,
        notes: location?.notes ?? '',
        manager_ids: location?.manager_ids ?? ([] as number[]),
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        if (method === 'patch') {
            form.patch(submitUrl, { preserveScroll: true });
            return;
        }
        form.post(submitUrl);
    }

    function toggleManager(id: number) {
        const next = form.data.manager_ids.includes(id)
            ? form.data.manager_ids.filter((value) => value !== id)
            : [...form.data.manager_ids, id];
        form.setData('manager_ids', next);
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="border-border bg-card space-y-6 rounded-xl border p-4 md:p-6"
            data-test="location-form"
        >
            <div className="space-y-1.5">
                <Label htmlFor="location-name">Name</Label>
                <Input
                    id="location-name"
                    data-test="location-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-1.5 md:col-span-2">
                    <Label htmlFor="location-address1">Address line 1</Label>
                    <Input
                        id="location-address1"
                        value={form.data.address_line1}
                        onChange={(e) =>
                            form.setData('address_line1', e.target.value)
                        }
                    />
                    <InputError message={form.errors.address_line1} />
                </div>
                <div className="space-y-1.5 md:col-span-2">
                    <Label htmlFor="location-address2">Address line 2</Label>
                    <Input
                        id="location-address2"
                        value={form.data.address_line2}
                        onChange={(e) =>
                            form.setData('address_line2', e.target.value)
                        }
                    />
                    <InputError message={form.errors.address_line2} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="location-city">City</Label>
                    <Input
                        id="location-city"
                        data-test="location-city"
                        value={form.data.city}
                        onChange={(e) => form.setData('city', e.target.value)}
                    />
                    <InputError message={form.errors.city} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="location-region">Region</Label>
                    <Input
                        id="location-region"
                        value={form.data.region}
                        onChange={(e) => form.setData('region', e.target.value)}
                    />
                    <InputError message={form.errors.region} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="location-postcode">Postcode</Label>
                    <Input
                        id="location-postcode"
                        value={form.data.postcode}
                        onChange={(e) =>
                            form.setData('postcode', e.target.value)
                        }
                    />
                    <InputError message={form.errors.postcode} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="location-country">Country</Label>
                    <Input
                        id="location-country"
                        value={form.data.country}
                        onChange={(e) =>
                            form.setData('country', e.target.value)
                        }
                    />
                    <InputError message={form.errors.country} />
                </div>
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="location-timezone">Timezone</Label>
                <select
                    id="location-timezone"
                    className={selectClassName}
                    value={form.data.timezone}
                    data-test="location-timezone"
                    onChange={(e) => form.setData('timezone', e.target.value)}
                >
                    {timezones.map((timezone) => (
                        <option key={timezone} value={timezone}>
                            {timezone}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.timezone} />
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="location-notes">Notes</Label>
                <textarea
                    id="location-notes"
                    className={cn(selectClassName, 'min-h-24 py-2')}
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                />
                <InputError message={form.errors.notes} />
            </div>

            {canAssignManagers && managerOptions.length > 0 ? (
                <div className="space-y-2" data-test="location-managers">
                    <Label>Location Managers</Label>
                    <p className="text-muted-foreground text-sm">
                        Assigned managers can only see and manage screens at
                        this location.
                    </p>
                    <ul className="space-y-2">
                        {managerOptions.map((manager) => (
                            <li key={manager.id}>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.manager_ids.includes(
                                            manager.id,
                                        )}
                                        onChange={() =>
                                            toggleManager(manager.id)
                                        }
                                    />
                                    <span>
                                        {manager.name}
                                        {manager.email
                                            ? ` · ${manager.email}`
                                            : ''}
                                    </span>
                                </label>
                            </li>
                        ))}
                    </ul>
                    <InputError message={form.errors.manager_ids} />
                </div>
            ) : null}

            <div className="flex justify-end">
                <Button
                    type="submit"
                    disabled={form.processing || !form.data.name.trim()}
                    data-test="location-submit"
                >
                    {form.processing ? <Spinner /> : null}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
