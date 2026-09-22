<?php

namespace App\Models;

use Database\Factories\PairingSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property Carbon|null $claimed_at
 * @property int|null $claimed_by
 * @property int|null $screen_id
 * @property array<string, mixed>|null $device_meta
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $pending_device_token_ciphertext
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PairingSession extends Model
{
    /** @use HasFactory<PairingSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'public_id',
        'code_hash',
        'expires_at',
        'claimed_at',
        'claimed_by',
        'screen_id',
        'device_meta',
        'ip_address',
        'user_agent',
        'pending_device_token_ciphertext',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'device_meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function claimedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    /**
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', strtoupper(trim($code)), (string) config('app.key'));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->lte(now());
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    public function isClaimable(): bool
    {
        return ! $this->isClaimed() && ! $this->isExpired();
    }

    public function verifyCode(string $code): bool
    {
        return hash_equals($this->code_hash, self::hashCode($code));
    }
}
