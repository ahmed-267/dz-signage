<?php

namespace App\Models;

use App\Support\Playlists\PlaylistRuntimeCalculator;
use Database\Factories\PlaylistVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $playlist_id
 * @property int $version_number
 * @property int|null $created_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PlaylistVersion extends Model
{
    /** @use HasFactory<PlaylistVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'playlist_id',
        'version_number',
        'created_by',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return HasMany<PlaylistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class, 'playlist_version_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Media assets referenced by the active items of this version. Single
     * source for what a player may fetch when this version is playing.
     *
     * @return list<int>
     */
    public function mediaAssetIds(): array
    {
        $this->loadMissing('items.screenDesignVersion');

        $ids = [];
        foreach ($this->items as $item) {
            if (! $item->is_active) {
                continue;
            }

            $schema = $item->screenDesignVersion?->schema;
            if (! is_array($schema)) {
                continue;
            }

            $ids = [...$ids, ...ScreenDesign::mediaIdsFromSchema($schema)];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Active-item runtime for this version (duration × loop_count).
     * Transitions are not included — see PlaylistRuntimeCalculator.
     */
    public function totalDurationSeconds(): int
    {
        return PlaylistRuntimeCalculator::totalSeconds($this);
    }
}
