<?php

namespace App\Models;

use App\Enums\PlaybackEventType;
use Database\Factories\PlaybackEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $screen_id
 * @property int|null $screen_device_id
 * @property PlaybackEventType $type
 * @property Carbon $occurred_at
 * @property int|null $deployment_id
 * @property int|null $screen_design_version_id
 * @property int|null $playlist_version_id
 * @property int|null $schedule_id
 * @property int|null $duration_seconds
 * @property string|null $error_code
 * @property string|null $idempotency_key
 * @property array<string, mixed>|null $meta
 */
class PlaybackEvent extends Model
{
    /** @use HasFactory<PlaybackEventFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'screen_id',
        'screen_device_id',
        'type',
        'occurred_at',
        'deployment_id',
        'screen_design_version_id',
        'playlist_version_id',
        'schedule_id',
        'duration_seconds',
        'error_code',
        'idempotency_key',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlaybackEventType::class,
            'occurred_at' => 'datetime',
            'meta' => 'array',
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
     * @return BelongsTo<ScreenDesignVersion, $this>
     */
    public function screenDesignVersion(): BelongsTo
    {
        return $this->belongsTo(ScreenDesignVersion::class);
    }

    /**
     * @return BelongsTo<PlaylistVersion, $this>
     */
    public function playlistVersion(): BelongsTo
    {
        return $this->belongsTo(PlaylistVersion::class);
    }

    /**
     * @return BelongsTo<Deployment, $this>
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }
}
