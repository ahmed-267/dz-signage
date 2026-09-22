<?php

namespace App\Models;

use Database\Factories\ScreenDailyStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $screen_id
 * @property Carbon $stat_date
 * @property int $online_seconds
 * @property int $offline_seconds
 * @property int $playback_seconds
 * @property int $content_play_count
 * @property int $error_count
 * @property int $heartbeat_count
 */
class ScreenDailyStat extends Model
{
    /** @use HasFactory<ScreenDailyStatFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'screen_id',
        'stat_date',
        'online_seconds',
        'offline_seconds',
        'playback_seconds',
        'content_play_count',
        'error_count',
        'heartbeat_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
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
}
