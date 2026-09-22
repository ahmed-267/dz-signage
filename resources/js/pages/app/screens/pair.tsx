import { Head, Link, router } from '@inertiajs/react';
import { Monitor } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ProductLabels } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import { screens as screensIndex } from '@/routes/app';
import screenRoutes from '@/routes/app/screens';
import type { ScreenPairProps } from '@/types/screen';

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

export default function ScreenPair({
    pairing,
    locations = [],
    require_location: requireLocation = false,
    workspace_name: workspaceName,
    can_manage: canManage,
}: ScreenPairProps) {
    const [name, setName] = useState(
        workspaceName ? `${workspaceName} TV` : 'New TV',
    );
    const [orientation, setOrientation] = useState('');
    const [locationId, setLocationId] = useState(
        locations.length === 1 ? String(locations[0].id) : '',
    );
    const [submitting, setSubmitting] = useState(false);

    const isPending = pairing.status === 'pending';
    const isUnavailable =
        pairing.status === 'expired' || pairing.status === 'claimed';
    const locationReady = !requireLocation || Boolean(locationId);

    function handleConfirm() {
        if (!name.trim() || !canManage || !locationReady) {
            return;
        }
        setSubmitting(true);
        router.post(
            screenRoutes.pair.claim.url(pairing.public_id),
            {
                name: name.trim(),
                orientation: orientation || null,
                location_id: locationId ? Number(locationId) : null,
            },
            {
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <>
            <Head title={ProductLabels.pairAction} />
            <div className="mx-auto flex h-full w-full max-w-lg flex-1 flex-col justify-center gap-6 p-4 md:p-6">
                <div className="border-border bg-card rounded-xl border p-6 shadow-sm">
                    <div className="mb-4 flex size-12 items-center justify-center rounded-full bg-cyan-500/15 text-cyan-600 dark:text-cyan-400">
                        <Monitor className="size-6" />
                    </div>

                    {isPending ? (
                        <>
                            <h1 className="font-display text-2xl font-semibold tracking-tight">
                                Pair this {ProductLabels.displaySingular} with{' '}
                                {workspaceName ?? 'your workspace'}?
                            </h1>
                            <p className="text-muted-foreground mt-2 text-sm">
                                Confirm to claim the pairing session from the
                                player device.
                            </p>

                            {canManage ? (
                                <div className="mt-6 space-y-4">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="pair-landing-name">
                                            TV name
                                        </Label>
                                        <Input
                                            id="pair-landing-name"
                                            data-test="pair-landing-name"
                                            value={name}
                                            onChange={(e) =>
                                                setName(e.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="pair-landing-orientation">
                                            Orientation (optional)
                                        </Label>
                                        <select
                                            id="pair-landing-orientation"
                                            className={selectClassName}
                                            value={orientation}
                                            onChange={(e) =>
                                                setOrientation(e.target.value)
                                            }
                                            data-test="pair-landing-orientation"
                                        >
                                            <option value="">Not set</option>
                                            <option value="landscape">
                                                Landscape
                                            </option>
                                            <option value="portrait">
                                                Portrait
                                            </option>
                                        </select>
                                    </div>
                                    {locations.length > 0 ? (
                                        <div className="space-y-1.5">
                                            <Label htmlFor="pair-landing-location">
                                                Location
                                                {requireLocation
                                                    ? ''
                                                    : ' (optional)'}
                                            </Label>
                                            <select
                                                id="pair-landing-location"
                                                className={selectClassName}
                                                value={locationId}
                                                onChange={(e) =>
                                                    setLocationId(
                                                        e.target.value,
                                                    )
                                                }
                                                data-test="pair-landing-location"
                                            >
                                                {!requireLocation ? (
                                                    <option value="">
                                                        Unassigned
                                                    </option>
                                                ) : null}
                                                {locations.map((location) => (
                                                    <option
                                                        key={location.id}
                                                        value={location.id}
                                                    >
                                                        {location.name}
                                                        {location.city
                                                            ? ` · ${location.city}`
                                                            : ''}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    ) : null}
                                    <Button
                                        type="button"
                                        className="w-full"
                                        data-test="pair-landing-confirm"
                                        disabled={
                                            submitting ||
                                            !name.trim() ||
                                            !locationReady
                                        }
                                        onClick={handleConfirm}
                                    >
                                        {submitting ? <Spinner /> : null}
                                        Confirm
                                    </Button>
                                </div>
                            ) : (
                                <p className="text-muted-foreground mt-6 text-sm">
                                    You do not have permission to pair screens
                                    in this business.
                                </p>
                            )}
                        </>
                    ) : null}

                    {isUnavailable ? (
                        <>
                            <h1 className="font-display text-2xl font-semibold tracking-tight">
                                {pairing.status === 'claimed'
                                    ? 'Already paired'
                                    : 'Pairing expired'}
                            </h1>
                            <p className="text-muted-foreground mt-2 text-sm">
                                {pairing.status === 'claimed'
                                    ? 'This pairing link has already been used.'
                                    : 'This pairing code or link is no longer valid. Start a new session from the player.'}
                            </p>
                            <Button
                                type="button"
                                className="mt-6"
                                variant="outline"
                                asChild
                            >
                                <Link href={screensIndex.url()}>
                                    {`Back to ${ProductLabels.pairedNav}`}
                                </Link>
                            </Button>
                        </>
                    ) : null}

                    {!isPending && !isUnavailable ? (
                        <>
                            <h1 className="font-display text-2xl font-semibold tracking-tight">
                                Unable to pair
                            </h1>
                            <p className="text-muted-foreground mt-2 text-sm">
                                This pairing session is not available.
                            </p>
                            <Button
                                type="button"
                                className="mt-6"
                                variant="outline"
                                asChild
                            >
                                <Link href={screensIndex.url()}>
                                    {`Back to ${ProductLabels.pairedNav}`}
                                </Link>
                            </Button>
                        </>
                    ) : null}
                </div>
            </div>
        </>
    );
}

ScreenPair.layout = {
    breadcrumbs: [
        { title: ProductLabels.pairedNav, href: screensIndex.url() },
        { title: 'Pair', href: '/app/screens' },
    ],
};
