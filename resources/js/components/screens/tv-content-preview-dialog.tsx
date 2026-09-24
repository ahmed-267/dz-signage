import { useEffect, useState } from 'react';
import {
    PlaylistPreviewPlayer,
    type PlaylistPlayerItem,
} from '@/components/playlists/playlist-preview-player';
import {
    toLayoutMediaMap,
    toPlayerItems,
} from '@/components/playlists/player-items';
import type { LayoutMediaMap } from '@/components/rendering/layout-renderer';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { PlaylistItem, PlaylistMediaMap } from '@/types/playlist';
import type { ScreenNowShowing } from '@/types/screen';

export type TvContentPreviewPayload = {
    screen_id: number;
    screen_name: string;
    orientation?: string | null;
    network_state?: string | null;
    now_showing?: ScreenNowShowing | null;
    preview_status?: string | null;
    expected_content_name?: string | null;
    reported_content_name?: string | null;
    is_offline?: boolean;
    is_out_of_sync?: boolean;
    source_label?: string | null;
    kind: 'empty' | 'screen' | 'playlist';
    content_name?: string | null;
    items: PlaylistItem[];
    media_map?: PlaylistMediaMap;
    canvas?: {
        width: number;
        height: number;
        orientation: string;
    };
};

type TvContentPreviewDialogProps = {
    screenId: number | null;
    screenName?: string;
    onClose: () => void;
};

type LoadState =
    | { status: 'loading' }
    | { status: 'error'; message: string }
    | { status: 'ready'; payload: TvContentPreviewPayload };

/**
 * Read-only preview of resolver-backed Now Showing content for a Paired TV.
 * Reuses PlaylistPreviewPlayer so transitions match Preview + Player.
 */
export function TvContentPreviewDialog({
    screenId,
    screenName,
    onClose,
}: TvContentPreviewDialogProps) {
    const [state, setState] = useState<LoadState>({ status: 'loading' });

    useEffect(() => {
        if (screenId === null) {
            return;
        }

        const controller = new AbortController();
        setState({ status: 'loading' });

        void (async () => {
            try {
                const response = await fetch(
                    `/app/screens/${screenId}/now-showing-preview`,
                    {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Preview request failed.');
                }

                const payload =
                    (await response.json()) as TvContentPreviewPayload;
                setState({ status: 'ready', payload });
            } catch (error) {
                if (controller.signal.aborted) {
                    return;
                }
                setState({
                    status: 'error',
                    message:
                        error instanceof Error
                            ? error.message
                            : 'Unable to load TV preview.',
                });
            }
        })();

        return () => controller.abort();
    }, [screenId]);

    const open = screenId !== null;
    const portrait =
        state.status === 'ready' &&
        (state.payload.canvas?.orientation === 'portrait' ||
            state.payload.orientation === 'portrait');

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent
                className={cn(
                    'flex max-h-[92vh] flex-col gap-4 overflow-hidden sm:max-w-4xl',
                    portrait && 'sm:max-w-xl',
                )}
                data-test="tv-content-preview-dialog"
            >
                <DialogHeader>
                    <DialogTitle className="font-display">
                        {state.status === 'ready'
                            ? state.payload.screen_name
                            : (screenName ?? 'TV Preview')}
                    </DialogTitle>
                    <DialogDescription>
                        Read-only preview of what this TV should be showing
                        right now. Opening preview does not publish or change
                        content.
                    </DialogDescription>
                </DialogHeader>

                {state.status === 'loading' ? (
                    <div className="flex min-h-64 items-center justify-center">
                        <Spinner className="size-6" />
                    </div>
                ) : null}

                {state.status === 'error' ? (
                    <p className="text-destructive text-sm">{state.message}</p>
                ) : null}

                {state.status === 'ready' ? (
                    <ReadyPreview payload={state.payload} portrait={portrait} />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function ReadyPreview({
    payload,
    portrait,
}: {
    payload: TvContentPreviewPayload;
    portrait: boolean;
}) {
    const items: PlaylistPlayerItem[] = toPlayerItems(
        (payload.items ?? []) as PlaylistItem[],
    );
    const mediaMap: LayoutMediaMap = toLayoutMediaMap(payload.media_map);
    const contentName =
        payload.content_name ??
        payload.now_showing?.content_name ??
        'No content';

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                        Now Showing
                    </p>
                    <p
                        className="truncate text-base font-medium"
                        data-test="tv-preview-content-name"
                    >
                        {contentName}
                    </p>
                    <p
                        className="text-muted-foreground text-xs"
                        data-test="tv-preview-source"
                    >
                        {payload.source_label}
                    </p>
                </div>
                <Badge
                    variant={
                        payload.is_offline
                            ? 'warning'
                            : payload.is_out_of_sync
                              ? 'warning'
                              : 'success'
                    }
                    data-test="tv-preview-status"
                >
                    {payload.preview_status}
                </Badge>
            </div>

            {payload.is_offline ? (
                <p
                    className="text-muted-foreground rounded-md border border-dashed px-3 py-2 text-xs"
                    data-test="tv-preview-offline-note"
                >
                    TV Offline
                    {payload.reported_content_name
                        ? ` — last reported content: ${payload.reported_content_name}`
                        : payload.expected_content_name
                          ? ` — last known content: ${payload.expected_content_name}`
                          : ''}
                    . This preview shows expected/cached content and may not
                    match the physical display while offline.
                </p>
            ) : null}

            {payload.is_out_of_sync && !payload.is_offline ? (
                <p
                    className="text-muted-foreground rounded-md border border-dashed px-3 py-2 text-xs"
                    data-test="tv-preview-sync-note"
                >
                    Out of Sync — Expected:{' '}
                    <span className="text-foreground font-medium">
                        {payload.expected_content_name ?? '—'}
                    </span>
                    {payload.reported_content_name ? (
                        <>
                            {' '}
                            · Reported:{' '}
                            <span className="text-foreground font-medium">
                                {payload.reported_content_name}
                            </span>
                        </>
                    ) : null}
                </p>
            ) : null}

            {items.length === 0 ? (
                <div className="bg-muted/40 flex min-h-64 items-center justify-center rounded-lg">
                    <p className="text-muted-foreground text-sm">
                        Nothing to preview — this TV has no resolved content.
                    </p>
                </div>
            ) : (
                <div
                    className={cn(
                        'min-h-0 w-full',
                        portrait
                            ? 'h-[min(70vh,640px)]'
                            : 'h-[min(60vh,480px)]',
                    )}
                >
                    <PlaylistPreviewPlayer
                        items={items}
                        mediaMap={mediaMap}
                        autoPlay
                        loop={payload.kind === 'playlist'}
                        showControls={payload.kind === 'playlist'}
                        surface="preview"
                        className="h-full"
                        emptyMessage="Nothing to preview for this TV."
                    />
                </div>
            )}
        </div>
    );
}
