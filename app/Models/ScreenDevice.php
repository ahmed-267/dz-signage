<?php

namespace App\Models;

use Database\Factories\ScreenDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $screen_id
 * @property string $device_identifier
 * @property string $device_token_hash
 * @property string|null $device_name
 * @property array<string, mixed>|null $platform_meta
 * @property Carbon|null $paired_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_seen_at
 * @property string|null $player_version
 * @property int|null $viewport_width
 * @property int|null $viewport_height
 * @property string|null $reported_orientation
 * @property string|null $playback_state
 * @property string|null $last_error_code
 * @property int|null $reported_deployment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScreenDevice extends Model
{
    /** @use HasFactory<ScreenDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'screen_id',
        'device_identifier',
        'device_token_hash',
        'device_name',
        'platform_meta',
        'paired_at',
        'revoked_at',
        'last_seen_at',
        'player_version',
        'viewport_width',
        'viewport_height',
        'reported_orientation',
        'playback_state',
        'last_error_code',
        'reported_deployment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform_meta' => 'array',
            'paired_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'viewport_width' => 'integer',
            'viewport_height' => 'integer',
            'reported_deployment_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public static function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    public static function findByToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }

        return static::query()
            ->where('device_token_hash', static::hashToken($token))
            ->first();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function touchLastSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }
}
