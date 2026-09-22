<?php

namespace App\Models;

use App\Enums\PlaylistTransition;
use App\Enums\PlaylistTransitionSpeed;
use Database\Factories\PlaylistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $playlist_version_id
 * @property int $screen_design_id
 * @property int $screen_design_version_id
 * @property int $position
 * @property int $duration_seconds
 * @property int $loop_count
 * @property PlaylistTransition $transition
 * @property PlaylistTransitionSpeed $transition_speed
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PlaylistItem extends Model
{
    /** @use HasFactory<PlaylistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'playlist_version_id',
        'screen_design_id',
        'screen_design_version_id',
        'position',
        'duration_seconds',
        'loop_count',
        'transition',
        'transition_speed',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'duration_seconds' => 'integer',
            'loop_count' => 'integer',
            'transition' => PlaylistTransition::class,
            'transition_speed' => PlaylistTransitionSpeed::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PlaylistVersion, $this>
     */
    public function playlistVersion(): BelongsTo
    {
        return $this->belongsTo(PlaylistVersion::class);
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
}
