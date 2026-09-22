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
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { formatDuration } from '@/lib/format-duration';
import playlistRoutes from '@/routes/app/playlists';
import type { PlaylistPreviewPayload } from '@/types/playlist';

type PlaylistPreviewDialogProps = {
    playlistId: number | null;
    playlistName?: string;
    onClose: () => void;
};

type LoadState =
    | { status: 'loading' }
    | { status: 'error'; message: string }
    | {
          status: 'ready';
          items: PlaylistPlayerItem[];
          mediaMap: LayoutMediaMap;
          totalSeconds: number;
          name: string;
      };

/**
 * Library preview. The preview endpoint returns JSON (not an Inertia page), so
 * the published — or latest draft — version is fetched on open.
 */
export function PlaylistPreviewDialog({
    playlistId,
    playlistName,
    onClose,
}: PlaylistPreviewDialogProps) {
    const [state, setState] = useState<LoadState>({ status: 'loading' });

    useEffect(() => {
        if (playlistId === null) {
            return;
        }

        const controller = new AbortController();
        setState({ status: 'loading' });

        void (async () => {
            try {
                const response = await fetch(
                    playlistRoutes.preview.url(playlistId),
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
                    (await response.json()) as PlaylistPreviewPayload;

                setState({
                    status: 'ready',
                    items: toPlayerItems(payload.items ?? []),
                    mediaMap: toLayoutMediaMap(payload.media_map),
                    totalSeconds: payload.total_duration_seconds ?? 0,
                    name: payload.name,
                });
            } catch (error) {
                if (controller.signal.aborted) {
                    return;
                }
                setState({
                    status: 'error',
                    message:
                        error instanceof Error
                            ? error.message
                            : 'Unable to load this playlist preview.',
                });
            }
        })();

        return () => controller.abort();
    }, [playlistId]);

    const title =
        state.status === 'ready' ? state.name : (playlistName ?? 'Playlist');

    return (
        <Dialog
            open={playlistId !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                className="flex max-h-[90vh] flex-col sm:max-w-[960px]"
                data-test="playlist-preview-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Preview · {title}</DialogTitle>
                    <DialogDescription>
                        {state.status === 'ready'
                            ? `Active items only · ${formatDuration(state.totalSeconds)} loop.`
                            : 'Sequential playback of the active items in this playlist.'}
                    </DialogDescription>
                </DialogHeader>

                {state.status === 'loading' ? (
                    <div className="flex h-[min(60vh,520px)] items-center justify-center">
                        <Spinner className="size-6" />
                    </div>
                ) : null}

                {state.status === 'error' ? (
                    <p
                        className="text-destructive py-12 text-center text-sm"
                        data-test="playlist-preview-error"
                    >
                        {state.message}
                    </p>
                ) : null}

                {state.status === 'ready' ? (
                    <PlaylistPreviewPlayer
                        items={state.items}
                        mediaMap={state.mediaMap}
                        className="h-[min(60vh,520px)]"
                        emptyMessage="This playlist has no active designs to play."
                    />
                ) : null}

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
