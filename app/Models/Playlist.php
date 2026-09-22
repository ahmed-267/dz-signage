<?php

namespace App\Models;

use App\Enums\PlaylistStatus;
use App\Enums\TemplateOrientation;
use Database\Factories\PlaylistFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string|null $description
 * @property TemplateOrientation|null $orientation
 * @property PlaylistStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $published_version_id
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Playlist extends Model
{
    /** @use HasFactory<PlaylistFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'orientation',
        'status',
        'created_by',
        'updated_by',
        'published_version_id',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orientation' => TemplateOrientation::class,
            'status' => PlaylistStatus::class,
            'published_at' => 'datetime',
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
     * @return HasMany<PlaylistVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PlaylistVersion::class);
    }

    /**
     * @return BelongsTo<PlaylistVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(PlaylistVersion::class, 'published_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * @param  Builder<Playlist>  $query
     * @return Builder<Playlist>
     */
    public function scopeForWorkspace(Builder $query, Workspace|int $workspace): Builder
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $query->where('workspace_id', $id);
    }

    public function latestVersion(): ?PlaylistVersion
    {
        return $this->versions()->orderByDesc('version_number')->first();
    }
}
