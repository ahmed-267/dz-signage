<?php

namespace App\Actions\Playlists;

use App\Enums\PlaylistTransition;
use App\Enums\PlaylistTransitionSpeed;
use App\Enums\TemplateOrientation;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistVersion;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\User;
use App\Support\Playlists\PlaylistDefaults;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves playlist metadata and — when `items` is present — replaces the item
 * list of the current draft version. Published versions are immutable, so a
 * new draft version is created (copying items) when the latest is published.
 */
class SavePlaylistDraft
{
    /**
     * @param  array{
     *     name?: string|null,
     *     description?: string|null,
     *     orientation?: string|null,
     *     items?: array<int, mixed>|null
     * }  $data
     */
    public function handle(User $user, Playlist $playlist, array $data): Playlist
    {
        return DB::transaction(function () use ($user, $playlist, $data) {
            $attributes = ['updated_by' => $user->id];

            if (array_key_exists('name', $data) && $data['name'] !== null) {
                $name = trim((string) $data['name']);
                if ($name === '') {
                    throw ValidationException::withMessages([
                        'name' => 'Playlist name is required.',
                    ]);
                }
                $attributes['name'] = $name;
            }

            if (array_key_exists('description', $data)) {
                $description = $data['description'] !== null ? trim((string) $data['description']) : null;
                $attributes['description'] = $description === '' ? null : $description;
            }

            $requestedOrientation = array_key_exists('orientation', $data) && $data['orientation'] !== null
                ? $this->orientation((string) $data['orientation'])
                : null;

            $version = $this->draftVersion($user, $playlist);

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $resolved = $this->resolveItems($playlist, $data['items']);
                $orientation = $this->orientationForItems($playlist, $requestedOrientation, $resolved);
                $this->replaceItems($version, $resolved);
                $attributes['orientation'] = $orientation;
            } elseif ($requestedOrientation !== null) {
                $this->assertOrientationChangeAllowed($version, $requestedOrientation);
                $attributes['orientation'] = $requestedOrientation;
            }

            $playlist->forceFill($attributes)->save();

            return $playlist->fresh(['versions.items']) ?? $playlist;
        });
    }

    private function orientation(string $value): TemplateOrientation
    {
        $orientation = TemplateOrientation::tryFrom($value);
        if ($orientation === null) {
            throw ValidationException::withMessages([
                'orientation' => 'Orientation must be landscape or portrait.',
            ]);
        }

        return $orientation;
    }

    /**
     * Latest unpublished version, or a new draft version copied from the
     * published latest so published versions stay immutable.
     */
    private function draftVersion(User $user, Playlist $playlist): PlaylistVersion
    {
        $latest = $playlist->versions()
            ->orderByDesc('version_number')
            ->lockForUpdate()
            ->first();

        if ($latest === null) {
            throw ValidationException::withMessages([
                'playlist' => 'Playlist has no versions to update.',
            ]);
        }

        if (! $latest->isPublished()) {
            return $latest;
        }

        $draft = PlaylistVersion::query()->create([
            'playlist_id' => $playlist->id,
            'version_number' => $latest->version_number + 1,
            'created_by' => $user->id,
            'published_at' => null,
        ]);

        foreach ($latest->items as $item) {
            PlaylistItem::query()->create([
                'playlist_version_id' => $draft->id,
                'screen_design_id' => $item->screen_design_id,
                'screen_design_version_id' => $item->screen_design_version_id,
                'position' => $item->position,
                'duration_seconds' => $item->duration_seconds,
                'loop_count' => $item->loop_count,
                'transition' => $item->transition,
                'transition_speed' => $item->transition_speed,
                'is_active' => $item->is_active,
            ]);
        }

        return $draft;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array{
     *     design: ScreenDesign,
     *     version: ScreenDesignVersion,
     *     duration_seconds: int,
     *     loop_count: int,
     *     transition: PlaylistTransition,
     *     transition_speed: PlaylistTransitionSpeed,
     *     is_active: bool
     * }>
     */
    private function resolveItems(Playlist $playlist, array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['screen_design_id'])) {
                throw ValidationException::withMessages([
                    'items' => 'Each playlist item requires a screen design.',
                ]);
            }
            $rows[] = $item;
        }

        if ($rows === []) {
            return [];
        }

        $designIds = array_map(static fn (array $row): int => (int) $row['screen_design_id'], $rows);

        /** @var Collection<int, ScreenDesign> $designs */
        $designs = ScreenDesign::query()
            ->forWorkspace($playlist->workspace_id)
            ->whereIn('id', array_values(array_unique($designIds)))
            ->with('publishedVersion')
            ->get()
            ->keyBy('id');

        if ($designs->count() !== count(array_unique($designIds))) {
            throw ValidationException::withMessages([
                'items' => 'One or more screen designs are invalid for this workspace.',
            ]);
        }

        $min = PlaylistDefaults::minDurationSeconds();
        $max = PlaylistDefaults::maxDurationSeconds();
        $minLoops = PlaylistDefaults::minLoopCount();
        $maxLoops = PlaylistDefaults::maxLoopCount();

        $resolved = [];
        foreach ($rows as $item) {
            /** @var ScreenDesign $design */
            $design = $designs->get((int) $item['screen_design_id']);
            $version = $this->resolveDesignVersion($design, $item['screen_design_version_id'] ?? null);

            $duration = array_key_exists('duration_seconds', $item) && $item['duration_seconds'] !== null
                ? (int) $item['duration_seconds']
                : PlaylistDefaults::durationSeconds();

            if ($duration < $min || $duration > $max) {
                throw ValidationException::withMessages([
                    'items' => "Item durations must be between {$min} and {$max} seconds.",
                ]);
            }

            $loopCount = array_key_exists('loop_count', $item) && $item['loop_count'] !== null
                ? (int) $item['loop_count']
                : PlaylistDefaults::loopCount();

            if ($loopCount < $minLoops || $loopCount > $maxLoops) {
                throw ValidationException::withMessages([
                    'items' => "Item loop counts must be between {$minLoops} and {$maxLoops}.",
                ]);
            }

            $resolved[] = [
                'design' => $design,
                'version' => $version,
                'duration_seconds' => $duration,
                'loop_count' => $loopCount,
                'transition' => $this->transition($item['transition'] ?? null),
                'transition_speed' => $this->transitionSpeed($item['transition_speed'] ?? null),
                'is_active' => ! array_key_exists('is_active', $item) || (bool) $item['is_active'],
            ];
        }

        return $resolved;
    }

    private function resolveDesignVersion(ScreenDesign $design, mixed $versionId): ScreenDesignVersion
    {
        if ($versionId !== null && $versionId !== '') {
            $version = ScreenDesignVersion::query()
                ->where('screen_design_id', $design->id)
                ->whereKey((int) $versionId)
                ->first();

            if ($version === null) {
                throw ValidationException::withMessages([
                    'items' => "The selected version of \"{$design->name}\" does not exist.",
                ]);
            }

            if (! $version->isPublished()) {
                throw ValidationException::withMessages([
                    'items' => "Playlists can only use published versions. \"{$design->name}\" version {$version->version_number} is still a draft.",
                ]);
            }

            return $version;
        }

        $published = $design->publishedVersion;
        if ($published === null || ! $published->isPublished()) {
            throw ValidationException::withMessages([
                'items' => "\"{$design->name}\" has no published version. Publish the design before adding it to a playlist.",
            ]);
        }

        return $published;
    }

    private function transition(mixed $value): PlaylistTransition
    {
        if ($value === null || $value === '') {
            return PlaylistDefaults::transition();
        }

        $transition = PlaylistTransition::tryFrom((string) $value);
        if ($transition === null) {
            throw ValidationException::withMessages([
                'items' => 'One or more item transitions are invalid.',
            ]);
        }

        return $transition;
    }

    private function transitionSpeed(mixed $value): PlaylistTransitionSpeed
    {
        if ($value === null || $value === '') {
            return PlaylistDefaults::transitionSpeed();
        }

        $speed = PlaylistTransitionSpeed::tryFrom((string) $value);
        if ($speed === null) {
            throw ValidationException::withMessages([
                'items' => 'One or more item transition speeds are invalid.',
            ]);
        }

        return $speed;
    }

    /**
     * The first item sets the playlist orientation; every other item must match.
     *
     * @param  list<array{design: ScreenDesign, version: ScreenDesignVersion, duration_seconds: int, loop_count: int, transition: PlaylistTransition, transition_speed: PlaylistTransitionSpeed, is_active: bool}>  $resolved
     */
    private function orientationForItems(
        Playlist $playlist,
        ?TemplateOrientation $requested,
        array $resolved,
    ): ?TemplateOrientation {
        if ($resolved === []) {
            return $requested ?? $playlist->orientation;
        }

        // Items are replaced wholesale, so the incoming set defines the
        // orientation. A stale value must not outlive the designs that set it.
        $orientation = $requested ?? $resolved[0]['design']->orientation;

        foreach ($resolved as $item) {
            $design = $item['design'];
            if ($design->orientation !== $orientation) {
                throw ValidationException::withMessages([
                    'items' => "This playlist is {$orientation->label()}. \"{$design->name}\" is {$design->orientation->label()} and cannot be mixed into the same playlist.",
                ]);
            }
        }

        return $orientation;
    }

    private function assertOrientationChangeAllowed(PlaylistVersion $version, TemplateOrientation $orientation): void
    {
        $conflicting = PlaylistItem::query()
            ->where('playlist_version_id', $version->id)
            ->whereHas('screenDesign', fn ($query) => $query->where('orientation', '!=', $orientation->value))
            ->exists();

        if ($conflicting) {
            throw ValidationException::withMessages([
                'orientation' => "This playlist cannot become {$orientation->label()} while it contains designs with a different orientation.",
            ]);
        }
    }

    /**
     * @param  list<array{design: ScreenDesign, version: ScreenDesignVersion, duration_seconds: int, loop_count: int, transition: PlaylistTransition, transition_speed: PlaylistTransitionSpeed, is_active: bool}>  $resolved
     */
    private function replaceItems(PlaylistVersion $version, array $resolved): void
    {
        $version->items()->delete();

        $position = 1;
        foreach ($resolved as $item) {
            PlaylistItem::query()->create([
                'playlist_version_id' => $version->id,
                'screen_design_id' => $item['design']->id,
                'screen_design_version_id' => $item['version']->id,
                'position' => $position++,
                'duration_seconds' => $item['duration_seconds'],
                'loop_count' => $item['loop_count'],
                'transition' => $item['transition'],
                'transition_speed' => $item['transition_speed'],
                'is_active' => $item['is_active'],
            ]);
        }
    }
}
