import { Head, Link, router } from '@inertiajs/react';
import { Archive, ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatLastSeen } from '@/lib/format-relative-time';
import { locations as locationsIndex } from '@/routes/app';
import locationRoutes from '@/routes/app/locations';
import screenRoutes from '@/routes/app/screens';
import type { LocationShowProps } from '@/types/location';
import { ProductLabels } from '@/lib/product-labels';

export default function LocationShow({
    location,
    screens,
    can_manage: canManage,
    can_delete: canDelete,
}: LocationShowProps) {
    const [archiveOpen, setArchiveOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    function handleArchive() {
        setBusy(true);
        router.post(
            locationRoutes.archive.url(location.id),
            {},
            {
                onFinish: () => {
                    setBusy(false);
                    setArchiveOpen(false);
                },
            },
        );
    }

    function handleDelete() {
        setBusy(true);
        router.delete(locationRoutes.destroy.url(location.id), {
            onFinish: () => {
                setBusy(false);
                setDeleteOpen(false);
            },
        });
    }

    return (
        <>
            <Head title={location.name} />
            <div
                className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="location-show"
            >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            {location.name}
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            {location.address ?? 'No address set'}
                            {location.is_archived ? ' · Archived' : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href={locationsIndex.url()}>
                                <ArrowLeft className="size-4" />
                                Locations
                            </Link>
                        </Button>
                        {canManage ? (
                            <>
                                <Button type="button" variant="outline" asChild>
                                    <Link
                                        href={locationRoutes.edit.url(
                                            location.id,
                                        )}
                                        data-test="location-edit"
                                    >
                                        <Pencil className="size-4" />
                                        Edit
                                    </Link>
                                </Button>
                                {!location.is_archived ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        data-test="location-archive"
                                        onClick={() => setArchiveOpen(true)}
                                    >
                                        <Archive className="size-4" />
                                        Archive
                                    </Button>
                                ) : null}
                                {canDelete ? (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        data-test="location-delete"
                                        onClick={() => setDeleteOpen(true)}
                                    >
                                        <Trash2 className="size-4" />
                                        Delete
                                    </Button>
                                ) : null}
                            </>
                        ) : null}
                    </div>
                </div>

                <section className="border-border bg-card rounded-xl border">
                    <h2 className="border-border border-b px-4 py-3 text-sm font-medium">
                        Overview
                    </h2>
                    <dl className="divide-border divide-y">
                        <DetailRow label="Timezone" value={location.timezone} />
                        <DetailRow
                            label={ProductLabels.displayPlural}
                            value={String(location.screen_count)}
                        />
                        <DetailRow
                            label="Presence"
                            value={`${location.online_count} online · ${location.offline_count} offline`}
                        />
                        <DetailRow
                            label="Managers"
                            value={
                                location.managers.length > 0
                                    ? location.managers
                                          .map((m) => m.name)
                                          .join(', ')
                                    : 'None assigned'
                            }
                        />
                        <DetailRow
                            label="Notes"
                            value={location.notes ?? '—'}
                        />
                    </dl>
                </section>

                <section className="border-border bg-card rounded-xl border">
                    <div className="border-border flex items-center justify-between border-b px-4 py-3">
                        <h2 className="text-sm font-medium">
                            {ProductLabels.displayPlural}
                        </h2>
                        <Badge variant="neutral">{screens.length}</Badge>
                    </div>
                    {screens.length === 0 ? (
                        <div className="p-4">
                            <EmptyState
                                title="No TVs at this location"
                                description="Assign a screen from the screen detail page or when pairing."
                            />
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Network
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Last seen
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {screens.map((screen) => (
                                    <TableRow key={screen.id}>
                                        <TableCell>
                                            <Link
                                                href={screenRoutes.show.url(
                                                    screen.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {screen.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {screen.operational_status_label}
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            <Badge
                                                variant={
                                                    screen.network_state ===
                                                    'online'
                                                        ? 'info'
                                                        : 'neutral'
                                                }
                                            >
                                                {screen.network_state ===
                                                'online'
                                                    ? 'Online'
                                                    : 'Offline'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden md:table-cell">
                                            {formatLastSeen(
                                                screen.last_seen_at,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </section>
            </div>

            <Dialog open={archiveOpen} onOpenChange={setArchiveOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Archive this location?</DialogTitle>
                        <DialogDescription>
                            TVs stay attached. The location is hidden from the
                            active list until restored by an owner.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setArchiveOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={busy}
                            onClick={handleArchive}
                        >
                            {busy ? <Spinner /> : null}
                            Archive
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this location?</DialogTitle>
                        <DialogDescription>
                            This permanently removes the location. TVs must be
                            moved or removed first.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={busy}
                            onClick={handleDelete}
                        >
                            {busy ? <Spinner /> : null}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-start justify-between gap-4 px-4 py-3 text-sm">
            <dt className="text-muted-foreground shrink-0">{label}</dt>
            <dd className="text-right">{value}</dd>
        </div>
    );
}

LocationShow.layout = {
    breadcrumbs: [
        { title: 'Locations', href: '/app/locations' },
        { title: 'Details', href: '#' },
    ],
};
