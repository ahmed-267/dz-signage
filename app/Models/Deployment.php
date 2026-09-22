<?php

namespace App\Models;

use App\Enums\DeploymentContentType;
use App\Enums\DeploymentStatus;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $screen_id
 * @property DeploymentContentType $content_type
 * @property int|null $screen_design_id
 * @property int|null $screen_design_version_id
 * @property int|null $playlist_id
 * @property int|null $playlist_version_id
 * @property DeploymentStatus $status
 * @property int|null $deployed_by
 * @property Carbon|null $deployed_at
 * @property Carbon|null $superseded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'screen_id',
        'content_type',
        'screen_design_id',
        'screen_design_version_id',
        'playlist_id',
        'playlist_version_id',
        'status',
        'deployed_by',
        'deployed_at',
        'superseded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_type' => DeploymentContentType::class,
            'status' => DeploymentStatus::class,
            'deployed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    /**
     * @return BelongsTo<ScreenDesign, $this>
     */
    public function screenDesign(): BelongsTo
    {
        return $this->belongsTo(ScreenDesign::class);
    }

    /**
     * @return BelongsTo<ScreenDesignVersion, $this>
     */
    public function screenDesignVersion(): BelongsTo
    {
        return $this->belongsTo(ScreenDesignVersion::class);
    }

    /**
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return BelongsTo<PlaylistVersion, $this>
     */
    public function playlistVersion(): BelongsTo
    {
        return $this->belongsTo(PlaylistVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deployer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployed_by');
    }

    public function isPlaylist(): bool
    {
        return $this->content_type === DeploymentContentType::Playlist;
    }

    public function versionLabel(): string
    {
        return 'dep-'.$this->id;
    }

    /**
     * Orientation of the deployed content, used for screen mismatch checks.
     */
    public function contentOrientation(): ?string
    {
        if ($this->isPlaylist()) {
            return $this->playlist?->orientation?->value;
        }

        return $this->screenDesign?->orientation?->value;
    }

    /**
     * Name of the deployed content, whichever kind it is.
     */
    public function contentName(): ?string
    {
        return $this->isPlaylist()
            ? $this->playlist?->name
            : $this->screenDesign?->name;
    }

    /**
     * Pinned version number of the deployed content, whichever kind it is.
     */
    public function contentVersionNumber(): ?int
    {
        return $this->isPlaylist()
            ? $this->playlistVersion?->version_number
            : $this->screenDesignVersion?->version_number;
    }

    /**
     * How many items the deployed playlist plays, or null for a design
     * deployment. Uses an eager-loaded `items_count` when one is available.
     */
    public function activePlaylistItemCount(): ?int
    {
        if (! $this->isPlaylist()) {
            return null;
        }

        $version = $this->playlistVersion;

        if ($version === null) {
            return 0;
        }

        $counted = $version->getAttribute('items_count');

        return $counted === null
            ? $version->items()->where('is_active', true)->count()
            : (int) $counted;
    }

    /**
     * Media assets the player is allowed to fetch for this deployment.
     *
     * @return list<int>
     */
    public function allowedMediaAssetIds(): array
    {
        if ($this->isPlaylist()) {
            $this->loadMissing('playlistVersion.items.screenDesignVersion');

            return $this->playlistVersion?->mediaAssetIds() ?? [];
        }

        $this->loadMissing('screenDesignVersion');
        $schema = $this->screenDesignVersion?->schema;

        return is_array($schema) ? ScreenDesign::mediaIdsFromSchema($schema) : [];
    }
}
