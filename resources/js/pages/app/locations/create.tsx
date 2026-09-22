import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { LocationForm } from '@/components/locations/location-form';
import { Button } from '@/components/ui/button';
import { locations as locationsIndex } from '@/routes/app';
import locationRoutes from '@/routes/app/locations';
import type { LocationCreateProps } from '@/types/location';

export default function LocationCreate(props: LocationCreateProps) {
    return (
        <>
            <Head title="Add Location" />
            <div
                className="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="location-create"
            >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Add Location
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            Create a site to group screens and assign managers.
                        </p>
                    </div>
                    <Button type="button" variant="outline" asChild>
                        <Link href={locationsIndex.url()}>
                            <ArrowLeft className="size-4" />
                            Locations
                        </Link>
                    </Button>
                </div>

                <LocationForm
                    {...props}
                    submitUrl={locationRoutes.store.url()}
                    submitLabel="Create location"
                />
            </div>
        </>
    );
}

LocationCreate.layout = {
    breadcrumbs: [
        { title: 'Locations', href: '/app/locations' },
        { title: 'Create', href: '/app/locations/create' },
    ],
};
