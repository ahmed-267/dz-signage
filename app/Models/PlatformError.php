<?php

namespace App\Models;

use App\Enums\PlatformErrorCategory;
use Database\Factories\PlatformErrorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * @property int $id
 * @property PlatformErrorCategory $category
 * @property int|null $workspace_id
 * @property int|null $screen_id
 * @property int|null $deployment_id
 * @property string $message
 * @property array<string, mixed>|null $metadata
 * @property Carbon $occurred_at
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PlatformError extends Model
{
    /** @use HasFactory<PlatformErrorFactory> */
    use HasFactory;

    protected $fillable = [
        'category',
        'workspace_id',
        'screen_id',
        'deployment_id',
        'message',
        'metadata',
        'occurred_at',
        'resolved_at',
        'resolved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PlatformErrorCategory::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<Deployment, $this>
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCategory(Builder $query, PlatformErrorCategory|string $category): Builder
    {
        return $query->where(
            'category',
            $category instanceof PlatformErrorCategory ? $category->value : $category,
        );
    }

    /**
     * Record a platform error lightly, deduping recent identical failures.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        PlatformErrorCategory|string $category,
        string $message,
        ?int $workspaceId = null,
        ?int $screenId = null,
        ?int $deploymentId = null,
        array $metadata = [],
    ): ?self {
        $categoryValue = $category instanceof PlatformErrorCategory
            ? $category->value
            : $category;

        $fingerprint = md5(implode('|', [
            $categoryValue,
            $message,
            (string) $workspaceId,
            (string) $screenId,
            (string) $deploymentId,
        ]));

        $cacheKey = 'platform_error:dedupe:'.$fingerprint;

        if (Cache::has($cacheKey)) {
            return null;
        }

        try {
            $error = static::query()->create([
                'category' => $categoryValue,
                'workspace_id' => $workspaceId,
                'screen_id' => $screenId,
                'deployment_id' => $deploymentId,
                'message' => mb_substr($message, 0, 255),
                'metadata' => $metadata === [] ? null : $metadata,
                'occurred_at' => now(),
            ]);

            Cache::put($cacheKey, true, now()->addMinutes(15));

            return $error;
        } catch (Throwable) {
            return null;
        }
    }
}
