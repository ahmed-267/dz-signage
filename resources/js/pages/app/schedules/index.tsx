import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Archive,
    CalendarClock,
    CalendarDays,
    Copy,
    Eye,
    ListVideo,
    MoonStar,
    MoreHorizontal,
    Pause,
    Pencil,
    Play,
    Plus,
    Search,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AiAgentDialog from '@/components/ai/ai-agent-dialog';
import {
    describeDays,
    formatTimeRange,
    formatWindow,
    statusBadgeVariant,
} from '@/components/schedules/schedule-format';
import { SchedulePreviewDialog } from '@/components/schedules/schedule-preview-dialog';
import { ScheduleWeekCalendar } from '@/components/schedules/schedule-week-calendar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { ListPagination } from '@/components/ui/list-pagination';
import { SortableTableHeader } from '@/components/ui/sortable-table-header';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useListSort } from '@/hooks/use-list-sort';
import { cn } from '@/lib/utils';
import { schedules as schedulesIndex } from '@/routes/app';
import scheduleRoutes from '@/routes/app/schedules';
import type { ScheduleListItem, SchedulesIndexProps } from '@/types/schedule';
import { ProductLabels, displayLabel } from '@/lib/product-labels';

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

type FilterPatch = Partial<{
    q: string;
    status: string;
    screen: string;
    playlist: string;
    sort: string;
    direction: string;
    week: string;
    page: number;
    per_page: number;
}>;

export default function SchedulesIndex({
    schedules: paginated,
    filters,
    calendar,
    statuses,
    screens,
    playlists,
    config,
    workspace_timezone: workspaceTimezone,
    can_manage: canManage,
}: SchedulesIndexProps) {
    const { ai } = usePage().props;
    const [searchInput, setSearchInput] = useState(filters.q);
    const [aiAgentOpen, setAiAgentOpen] = useState(false);
    const [previewSchedule, setPreviewSchedule] =
        useState<ScheduleListItem | null>(null);
    const [deleteSchedule, setDeleteSchedule] =
        useState<ScheduleListItem | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);

    const visibleIds = useMemo(
        () => paginated.data.map((schedule) => schedule.id),
        [paginated.data],
    );

    useEffect(() => {
        setSelected((prev) => prev.filter((id) => visibleIds.includes(id)));
    }, [visibleIds]);

    function toggleSelected(id: number, checked: boolean) {
        setSelected((prev) =>
            checked
                ? [...new Set([...prev, id])]
                : prev.filter((x) => x !== id),
        );
    }

    const allSelected =
        paginated.data.length > 0 && selected.length === paginated.data.length;

    function handleBulkDelete() {
        if (selected.length === 0) {
            return;
        }
        setBulkDeleting(true);
        router.post(
            scheduleRoutes.bulk_destroy.url(),
            { ids: selected },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBulkDeleting(false);
                    setBulkDeleteOpen(false);
                    setSelected([]);
                },
            },
        );
    }

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: schedulesIndex.url(),
        filters: {
            q: filters.q,
            status: filters.status,
            screen: filters.screen,
            playlist: filters.playlist,
            sort: filters.sort,
            direction: filters.direction,
            per_page: filters.per_page,
        },
        defaultSort: 'updated',
        defaultDirection: 'desc',
    });

    useEffect(() => {
        setSearchInput(filters.q);
    }, [filters.q]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (searchInput === filters.q) {
                return;
            }
            navigate({ q: searchInput });
        }, 350);

        return () => window.clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce against filters snapshot
    }, [searchInput]);

    function navigate(patch: FilterPatch) {
        const resetsPage =
            patch.q !== undefined ||
            patch.status !== undefined ||
            patch.screen !== undefined ||
            patch.playlist !== undefined ||
            patch.sort !== undefined ||
            patch.direction !== undefined ||
            patch.per_page !== undefined;

        router.get(
            schedulesIndex.url(),
            {
                q: patch.q ?? filters.q,
                status: patch.status ?? filters.status,
                screen:
                    patch.screen ??
                    (filters.screen ? String(filters.screen) : ''),
                playlist:
                    patch.playlist ??
                    (filters.playlist ? String(filters.playlist) : ''),
                sort: patch.sort ?? filters.sort,
                direction: patch.direction ?? filters.direction,
                per_page: patch.per_page ?? filters.per_page ?? 20,
                ...(patch.week !== undefined ? { week: patch.week } : {}),
                page:
                    resetsPage && patch.page === undefined
                        ? 1
                        : (patch.page ?? undefined),
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }

    /** Every list action is a POST that redirects back to this page. */
    function runAction(schedule: ScheduleListItem, url: string) {
        setBusyId(schedule.id);
        router.post(
            url,
            {},
            { preserveScroll: true, onFinish: () => setBusyId(null) },
        );
    }

    function handleDelete() {
        if (!deleteSchedule) {
            return;
        }
        setBusyId(deleteSchedule.id);
        router.delete(scheduleRoutes.destroy.url(deleteSchedule.id), {
            preserveScroll: true,
            onFinish: () => {
                setBusyId(null);
                setDeleteSchedule(null);
            },
        });
    }

    const total = paginated.meta.total;

    return (
        <>
            <Head title="Schedules" />
            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Schedules
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            {total} schedule{total === 1 ? '' : 's'} · workspace
                            time {workspaceTimezone}
                        </p>
                    </div>
                    {canManage ? (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                data-test="schedules-create-ai"
                                onClick={() => setAiAgentOpen(true)}
                            >
                                <Sparkles className="size-4" />
                                Create with AI
                            </Button>
                            <Button
                                type="button"
                                data-test="schedules-create"
                                asChild
                            >
                                <Link href={scheduleRoutes.create.url()}>
                                    <Plus className="size-4" />
                                    Create Schedule
                                </Link>
                            </Button>
                        </div>
                    ) : null}
                </div>

                <Tabs defaultValue="list" className="gap-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <TabsList data-test="schedules-tabs">
                            <TabsTrigger
                                value="list"
                                data-test="schedules-tab-list"
                            >
                                <ListVideo className="size-4" />
                                List
                            </TabsTrigger>
                            <TabsTrigger
                                value="calendar"
                                data-test="schedules-tab-calendar"
                            >
                                <CalendarDays className="size-4" />
                                Week
                            </TabsTrigger>
                        </TabsList>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="relative flex-1 sm:min-w-56">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    data-test="schedules-search"
                                    value={searchInput}
                                    onChange={(e) =>
                                        setSearchInput(e.target.value)
                                    }
                                    placeholder="Search schedules..."
                                    aria-label="Search schedules"
                                    className="pl-9"
                                />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <select
                                    className={cn(
                                        selectClassName,
                                        'w-auto min-w-32',
                                    )}
                                    value={filters.status}
                                    onChange={(e) =>
                                        navigate({ status: e.target.value })
                                    }
                                    aria-label="Status"
                                    data-test="schedules-status"
                                >
                                    <option value="all">All statuses</option>
                                    {statuses.map((status) => (
                                        <option
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    className={cn(
                                        selectClassName,
                                        'w-auto min-w-32',
                                    )}
                                    value={
                                        filters.screen
                                            ? String(filters.screen)
                                            : ''
                                    }
                                    onChange={(e) =>
                                        navigate({ screen: e.target.value })
                                    }
                                    aria-label="Screen"
                                    data-test="schedules-screen-filter"
                                >
                                    <option value="">All screens</option>
                                    {screens.map((screen) => (
                                        <option
                                            key={screen.id}
                                            value={screen.id}
                                        >
                                            {screen.name}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    className={cn(
                                        selectClassName,
                                        'w-auto min-w-32',
                                    )}
                                    value={
                                        filters.playlist
                                            ? String(filters.playlist)
                                            : ''
                                    }
                                    onChange={(e) =>
                                        navigate({ playlist: e.target.value })
                                    }
                                    aria-label="Playlist"
                                    data-test="schedules-playlist-filter"
                                >
                                    <option value="">All playlists</option>
                                    {playlists.map((playlist) => (
                                        <option
                                            key={playlist.id}
                                            value={playlist.id}
                                        >
                                            {playlist.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    <TabsContent value="list" className="space-y-4">
                        {selected.length > 0 && canManage ? (
                            <div
                                data-test="schedules-bulk-bar"
                                className="border-border bg-card sticky top-2 z-10 flex flex-wrap items-center gap-2 rounded-lg border p-3"
                            >
                                <span className="text-sm font-medium">
                                    {selected.length} selected
                                </span>
                                <div className="flex flex-wrap gap-2 sm:ml-auto">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        data-test="schedules-bulk-delete"
                                        onClick={() => setBulkDeleteOpen(true)}
                                    >
                                        <Trash2 className="size-3.5" />
                                        Delete
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setSelected([])}
                                    >
                                        Clear
                                    </Button>
                                </div>
                            </div>
                        ) : null}

                        {paginated.data.length === 0 ? (
                            <EmptyState
                                icon={CalendarClock}
                                title="No schedules yet"
                                description={
                                    canManage
                                        ? 'Create a schedule to automatically play playlists on selected screens at the right time.'
                                        : 'No schedules match your filters.'
                                }
                                action={
                                    canManage ? (
                                        <Button
                                            type="button"
                                            data-test="schedules-empty-create"
                                            asChild
                                        >
                                            <Link
                                                href={scheduleRoutes.create.url()}
                                            >
                                                <Plus className="size-4" />
                                                Create Schedule
                                            </Link>
                                        </Button>
                                    ) : undefined
                                }
                            />
                        ) : (
                            <div
                                className="border-border bg-card overflow-hidden rounded-xl border"
                                data-test="schedules-table"
                            >
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            {canManage ? (
                                                <TableHead className="w-10 pl-4">
                                                    <Checkbox
                                                        checked={allSelected}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) => {
                                                            if (checked) {
                                                                setSelected(
                                                                    visibleIds,
                                                                );
                                                            } else {
                                                                setSelected([]);
                                                            }
                                                        }}
                                                        aria-label="Select all schedules on this page"
                                                        data-test="schedules-select-all"
                                                    />
                                                </TableHead>
                                            ) : null}
                                            <TableHead
                                                className={
                                                    canManage
                                                        ? undefined
                                                        : 'pl-4'
                                                }
                                                aria-sort={ariaSort('name')}
                                            >
                                                <SortableTableHeader
                                                    label="Name"
                                                    column="name"
                                                    currentSort={currentSort}
                                                    currentDirection={
                                                        currentDirection
                                                    }
                                                    onSort={onSort}
                                                />
                                            </TableHead>
                                            <TableHead>Playlist</TableHead>
                                            <TableHead>
                                                {ProductLabels.displayPlural}
                                            </TableHead>
                                            <TableHead>Time</TableHead>
                                            <TableHead>Days</TableHead>
                                            <TableHead>Timezone</TableHead>
                                            <TableHead
                                                aria-sort={ariaSort('priority')}
                                            >
                                                <SortableTableHeader
                                                    label="Priority"
                                                    column="priority"
                                                    currentSort={currentSort}
                                                    currentDirection={
                                                        currentDirection
                                                    }
                                                    onSort={onSort}
                                                />
                                            </TableHead>
                                            <TableHead
                                                aria-sort={ariaSort('status')}
                                            >
                                                <SortableTableHeader
                                                    label="Status"
                                                    column="status"
                                                    currentSort={currentSort}
                                                    currentDirection={
                                                        currentDirection
                                                    }
                                                    onSort={onSort}
                                                />
                                            </TableHead>
                                            <TableHead>Next run</TableHead>
                                            <TableHead
                                                aria-sort={ariaSort('updated')}
                                            >
                                                <SortableTableHeader
                                                    label="Updated"
                                                    column="updated"
                                                    currentSort={currentSort}
                                                    currentDirection={
                                                        currentDirection
                                                    }
                                                    onSort={onSort}
                                                />
                                            </TableHead>
                                            <TableHead className="pr-4 text-right">
                                                <span className="sr-only">
                                                    Actions
                                                </span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {paginated.data.map((schedule) => (
                                            <ScheduleRow
                                                key={schedule.id}
                                                schedule={schedule}
                                                shortLabels={
                                                    config.day_short_labels
                                                }
                                                canManage={canManage}
                                                busy={busyId === schedule.id}
                                                selected={selected.includes(
                                                    schedule.id,
                                                )}
                                                onToggleSelected={(checked) =>
                                                    toggleSelected(
                                                        schedule.id,
                                                        checked,
                                                    )
                                                }
                                                onPreview={() =>
                                                    setPreviewSchedule(schedule)
                                                }
                                                onAction={runAction}
                                                onDelete={() =>
                                                    setDeleteSchedule(schedule)
                                                }
                                            />
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {paginated.meta.total > 0 ? (
                            <ListPagination
                                page={paginated.meta.current_page}
                                pageCount={paginated.meta.last_page}
                                total={paginated.meta.total}
                                from={paginated.meta.from ?? null}
                                to={paginated.meta.to ?? null}
                                perPage={paginated.meta.per_page}
                                data-test="schedules-pagination"
                                onPageChange={(page) => navigate({ page })}
                                onPerPageChange={(per_page) =>
                                    navigate({ per_page, page: 1 })
                                }
                            />
                        ) : null}
                    </TabsContent>

                    <TabsContent value="calendar">
                        <ScheduleWeekCalendar
                            calendar={calendar}
                            onWeekChange={(week) => navigate({ week })}
                            onSelect={(scheduleId) => {
                                const match = paginated.data.find(
                                    (schedule) => schedule.id === scheduleId,
                                );
                                if (match) {
                                    setPreviewSchedule(match);
                                    return;
                                }
                                router.visit(
                                    scheduleRoutes.edit.url(scheduleId),
                                );
                            }}
                        />
                    </TabsContent>
                </Tabs>
            </div>

            <SchedulePreviewDialog
                scheduleId={previewSchedule?.id ?? null}
                scheduleName={previewSchedule?.name}
                shortLabels={config.day_short_labels}
                onClose={() => setPreviewSchedule(null)}
            />

            <Dialog
                open={deleteSchedule !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteSchedule(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Delete “{deleteSchedule?.name}”?
                        </DialogTitle>
                        <DialogDescription>
                            {deleteSchedule?.stored_status === 'active'
                                ? 'This schedule is live. Pause or archive it before deleting it.'
                                : 'This permanently deletes the schedule. The playlist and screens it targets are not affected.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteSchedule(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            data-test="schedule-delete-confirm"
                            disabled={
                                deleteSchedule?.stored_status === 'active'
                            }
                            onClick={handleDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={bulkDeleteOpen}
                onOpenChange={(open) => {
                    if (!open && !bulkDeleting) {
                        setBulkDeleteOpen(false);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Delete {selected.length}{' '}
                            {selected.length === 1 ? 'Schedule' : 'Schedules'}?
                        </DialogTitle>
                        <DialogDescription>
                            This permanently deletes the selected schedules.
                            Live schedules must be paused or archived first and
                            will be skipped when that rule applies.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={bulkDeleting}
                            onClick={() => setBulkDeleteOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            data-test="schedules-bulk-delete-confirm"
                            disabled={bulkDeleting}
                            onClick={handleBulkDelete}
                        >
                            {bulkDeleting ? <Spinner /> : null}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AiAgentDialog
                open={aiAgentOpen}
                onOpenChange={setAiAgentOpen}
                available={ai?.available ?? false}
                unavailableMessage={ai?.message}
                preferredIntent="schedule"
                title="Create schedule with AI"
            />
        </>
    );
}

function ScheduleRow({
    schedule,
    shortLabels,
    canManage,
    busy,
    selected,
    onToggleSelected,
    onPreview,
    onAction,
    onDelete,
}: {
    schedule: ScheduleListItem;
    shortLabels: Record<string, string>;
    canManage: boolean;
    busy: boolean;
    selected: boolean;
    onToggleSelected: (checked: boolean) => void;
    onPreview: () => void;
    onAction: (schedule: ScheduleListItem, url: string) => void;
    onDelete: () => void;
}) {
    const isArchived = schedule.stored_status === 'archived';
    const conflictCount = schedule.conflict_count ?? 0;

    return (
        <TableRow
            data-test={`schedule-row-${schedule.id}`}
            data-status={schedule.status}
            data-priority={schedule.priority}
            data-live={schedule.is_live ? 'true' : 'false'}
        >
            {canManage ? (
                <TableCell className="pl-4">
                    <Checkbox
                        checked={selected}
                        onCheckedChange={(checked) =>
                            onToggleSelected(Boolean(checked))
                        }
                        aria-label={`Select ${schedule.name}`}
                        data-test={`schedule-select-${schedule.id}`}
                    />
                </TableCell>
            ) : null}
            <TableCell className={canManage ? 'max-w-56' : 'max-w-56 pl-4'}>
                <div className="flex items-center gap-2">
                    <span className="truncate font-medium">
                        {schedule.name}
                    </span>
                    {schedule.is_live ? (
                        <Badge
                            variant="success"
                            data-test={`schedule-live-${schedule.id}`}
                        >
                            On now
                        </Badge>
                    ) : null}
                    {conflictCount > 0 ? (
                        <Badge
                            variant="warning"
                            data-test={`schedule-conflicts-${schedule.id}`}
                            title="Overlaps are warnings only — higher priority content will play."
                        >
                            ⚠ {conflictCount} overlap
                            {conflictCount === 1 ? '' : 's'}
                        </Badge>
                    ) : null}
                </div>
                {schedule.description ? (
                    <p className="text-muted-foreground truncate text-xs">
                        {schedule.description}
                    </p>
                ) : null}
            </TableCell>
            <TableCell className="max-w-44">
                <span
                    className="block truncate"
                    data-test={`schedule-playlist-${schedule.id}`}
                >
                    {schedule.playlist_name ?? '—'}
                </span>
                {schedule.playlist_version_number != null ? (
                    <span className="text-muted-foreground text-xs">
                        v{schedule.playlist_version_number}
                    </span>
                ) : null}
            </TableCell>
            <TableCell data-test={`schedule-screens-${schedule.id}`}>
                {schedule.screen_count === 1
                    ? '1 Screen'
                    : `${schedule.screen_count} ${displayLabel(schedule.screen_count)}`}
            </TableCell>
            <TableCell>
                <div className="space-y-0.5">
                    <span
                        className="inline-flex items-center gap-1 font-mono text-sm"
                        data-test={`schedule-time-${schedule.id}`}
                    >
                        {formatTimeRange(
                            schedule.start_time,
                            schedule.end_time,
                        )}
                        {schedule.crosses_midnight ? (
                            <MoonStar
                                className="text-muted-foreground size-3.5"
                                aria-label="Runs past midnight"
                            />
                        ) : null}
                    </span>
                    <p className="text-muted-foreground text-xs">
                        {schedule.timezone}
                        {schedule.crosses_midnight ? ' · Overnight' : ''}
                    </p>
                </div>
            </TableCell>
            <TableCell>
                <span
                    className="text-sm"
                    data-test={`schedule-days-${schedule.id}`}
                    title={schedule.day_labels.join(', ')}
                >
                    {schedule.day_labels.length === 0
                        ? 'No days'
                        : describeDays(schedule.days_of_week, shortLabels)}
                </span>
            </TableCell>
            <TableCell className="text-muted-foreground text-xs">
                {schedule.timezone}
            </TableCell>
            <TableCell data-test={`schedule-priority-${schedule.id}`}>
                {schedule.priority}
            </TableCell>
            <TableCell>
                <Badge
                    variant={statusBadgeVariant(schedule.status)}
                    data-test={`schedule-status-${schedule.id}`}
                >
                    {schedule.status_label}
                </Badge>
            </TableCell>
            <TableCell
                className="font-mono text-xs"
                data-test={`schedule-next-${schedule.id}`}
            >
                {formatWindow(schedule.next_window)}
            </TableCell>
            <TableCell className="text-muted-foreground text-xs">
                {schedule.updated_at
                    ? new Date(schedule.updated_at).toLocaleDateString()
                    : '—'}
            </TableCell>
            <TableCell className="pr-4 text-right">
                {canManage ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-7"
                                aria-label={`Actions for ${schedule.name}`}
                                data-test={`schedule-menu-${schedule.id}`}
                                disabled={busy}
                            >
                                {busy ? (
                                    <Spinner />
                                ) : (
                                    <MoreHorizontal className="size-4" />
                                )}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {isArchived ? null : (
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={scheduleRoutes.edit.url(
                                            schedule.id,
                                        )}
                                        data-test={`schedule-edit-${schedule.id}`}
                                    >
                                        <Pencil className="size-3.5" />
                                        Edit
                                    </Link>
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem
                                onClick={onPreview}
                                data-test={`schedule-preview-${schedule.id}`}
                            >
                                <Eye className="size-3.5" />
                                Preview
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() =>
                                    onAction(
                                        schedule,
                                        scheduleRoutes.duplicate.url(
                                            schedule.id,
                                        ),
                                    )
                                }
                                data-test={`schedule-duplicate-${schedule.id}`}
                            >
                                <Copy className="size-3.5" />
                                Duplicate
                            </DropdownMenuItem>
                            {!isArchived &&
                            schedule.stored_status !== 'active' ? (
                                <DropdownMenuItem
                                    onClick={() =>
                                        onAction(
                                            schedule,
                                            scheduleRoutes.activate.url(
                                                schedule.id,
                                            ),
                                        )
                                    }
                                    data-test={`schedule-activate-${schedule.id}`}
                                >
                                    <Play className="size-3.5" />
                                    Activate
                                </DropdownMenuItem>
                            ) : null}
                            {schedule.stored_status === 'active' ? (
                                <DropdownMenuItem
                                    onClick={() =>
                                        onAction(
                                            schedule,
                                            scheduleRoutes.pause.url(
                                                schedule.id,
                                            ),
                                        )
                                    }
                                    data-test={`schedule-pause-${schedule.id}`}
                                >
                                    <Pause className="size-3.5" />
                                    Pause
                                </DropdownMenuItem>
                            ) : null}
                            {isArchived ? null : (
                                <DropdownMenuItem
                                    onClick={() =>
                                        onAction(
                                            schedule,
                                            scheduleRoutes.archive.url(
                                                schedule.id,
                                            ),
                                        )
                                    }
                                    data-test={`schedule-archive-${schedule.id}`}
                                >
                                    <Archive className="size-3.5" />
                                    Archive
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={onDelete}
                                data-test={`schedule-delete-${schedule.id}`}
                            >
                                <Trash2 className="size-3.5" />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={onPreview}
                        data-test={`schedule-preview-${schedule.id}`}
                    >
                        <Eye className="size-3.5" />
                        Preview
                    </Button>
                )}
            </TableCell>
        </TableRow>
    );
}

SchedulesIndex.layout = {
    breadcrumbs: [{ title: 'Schedules', href: '/app/schedules' }],
};
