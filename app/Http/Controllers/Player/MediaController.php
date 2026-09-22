<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Screen;
use App\Support\Screens\ScreenContentResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function __construct(private readonly ScreenContentResolver $resolver) {}

    public function show(Request $request, MediaAsset $mediaAsset): StreamedResponse
    {
        /** @var Screen $screen */
        $screen = $request->attributes->get('player_screen');

        abort_unless(
            (int) $mediaAsset->workspace_id === (int) $screen->workspace_id,
            403,
        );

        abort_unless(in_array((int) $mediaAsset->id, $this->allowedMediaAssetIds($screen), true), 403);
        abort_unless($mediaAsset->hasStoredFile(), 404);

        $disk = Storage::disk($mediaAsset->storage_disk);
        abort_unless($disk->exists($mediaAsset->storage_path), 404);

        return $disk->response(
            $mediaAsset->storage_path,
            $mediaAsset->original_filename ?: $mediaAsset->name,
            [
                'Content-Type' => $mediaAsset->mime_type ?: 'application/octet-stream',
            ],
        );
    }

    /**
     * Assets the player may fetch: whatever the resolver says should be
     * playing, plus the active deployment while a schedule is on top of it, so
     * a window changeover cannot 403 the content still on screen.
     *
     * @return list<int>
     */
    private function allowedMediaAssetIds(Screen $screen): array
    {
        $content = $this->resolver->resolve($screen);
        $allowed = $content->allowedMediaAssetIds();

        if ($content->isSchedule()) {
            $deployment = $screen->activeDeployment();

            if ($deployment !== null) {
                $allowed = [...$allowed, ...$deployment->allowedMediaAssetIds()];
            }
        }

        return array_values(array_unique($allowed));
    }
}
