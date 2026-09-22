<?php

namespace App\Actions\Playlists;

use App\Models\Playlist;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BulkDeletePlaylists
{
    public function __construct(private readonly DeletePlaylist $delete) {}

    /**
     * @param  list<int>  $ids
     * @return array{deleted: list<int>, failed: list<array{id: int, name: string, reason: string}>}
     */
    public function handle(User $user, Workspace $workspace, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $deleted = [];
        $failed = [];

        $playlists = Playlist::query()
            ->forWorkspace($workspace)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $playlist = $playlists->get($id);

            if ($playlist === null) {
                $failed[] = [
                    'id' => $id,
                    'name' => 'Unknown',
                    'reason' => 'Not found in this workspace.',
                ];

                continue;
            }

            try {
                Gate::forUser($user)->authorize('delete', $playlist);
                $this->delete->handle($playlist);
                $deleted[] = $id;
            } catch (AuthorizationException) {
                $failed[] = [
                    'id' => $id,
                    'name' => $playlist->name,
                    'reason' => 'You are not allowed to delete this playlist.',
                ];
            } catch (ValidationException $e) {
                $failed[] = [
                    'id' => $id,
                    'name' => $playlist->name,
                    'reason' => $this->firstError($e),
                ];
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    private function firstError(ValidationException $e): string
    {
        $messages = collect($e->errors())->flatten()->filter();

        return (string) ($messages->first() ?? 'Could not delete this playlist.');
    }
}
