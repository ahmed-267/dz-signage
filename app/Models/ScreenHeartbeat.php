<?php

namespace App\Models;

use Database\Factories\ScreenHeartbeatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $screen_id
 * @property int $screen_device_id
 * @property Carbon $recorded_at
 * @property string|null $player_version
 * @property string|null $user_agent
 * @property int|null $viewport_width
 * @property int|null $viewport_height
 * @property string|null $orientation
 * @property int|null $deployment_id
 * @property int|null $screen_design_version_id
 * @property string|null $playback_state
 * @property string|null $error_code
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScreenHeartbeat extends Model
{
    /** @use HasFactory<ScreenHeartbeatFactory> */
    use HasFactory;

    protected $fillable = [
        'screen_id',
        'screen_device_id',
        'recorded_at',
        'player_version',
        'user_agent',
        'viewport_width',
        'viewport_height',
        'orientation',
        'deployment_id',
        'screen_design_version_id',
        'playback_state',
        'error_code',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'viewport_width' => 'integer',
            'viewport_height' => 'integer',
            'deployment_id' => 'integer',
            'screen_design_version_id' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    /**
     * @return BelongsTo<ScreenDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(ScreenDevice::class, 'screen_device_id');
    }

    /**
     * @return BelongsTo<Deployment, $this>
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }
}
