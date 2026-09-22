<?php

namespace App\Models;

use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestPriority;
use App\Enums\SupportRequestStatus;
use Database\Factories\SupportRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property string $subject
 * @property SupportRequestCategory $category
 * @property string $message
 * @property SupportRequestPriority $priority
 * @property SupportRequestStatus $status
 * @property string|null $admin_notes
 * @property int|null $assigned_to
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SupportRequest extends Model
{
    /** @use HasFactory<SupportRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'subject',
        'category',
        'message',
        'priority',
        'status',
        'admin_notes',
        'assigned_to',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SupportRequestCategory::class,
            'priority' => SupportRequestPriority::class,
            'status' => SupportRequestStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<SupportRequestNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(SupportRequestNote::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SupportRequestStatus::Open->value,
            SupportRequestStatus::InProgress->value,
        ]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeStatus(Builder $query, SupportRequestStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof SupportRequestStatus ? $status->value : $status);
    }
}
