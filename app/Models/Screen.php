<?php

namespace App\Models;

use App\Enums\DeploymentStatus;
use App\Enums\ScreenOperationalStatus;
use App\Support\Screens\ScreenPresence;
use Database\Factories\ScreenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property int|null $location_id
 * @property string|null $orientation
 * @property ScreenOperationalStatus $operational_status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Screen extends Model
{
    /** @use HasFactory<ScreenFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'location_id',
        'orientation',
        'operational_status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operational_status' => ScreenOperationalStatus::class,
            'location_id' => 'integer',
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
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ScreenDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(ScreenDevice::class);
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * Schedules targeting this screen. Whether one is playing is decided by
     * `ScheduleEvaluator`, never here.
     *
     * @return BelongsToMany<Schedule, $this>
     */
    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(Schedule::class, 'schedule_screen')->withTimestamps();
    }

    /**
     * @return HasMany<PairingSession, $this>
     */
    public function pairingSessions(): HasMany
    {
        return $this->hasMany(PairingSession::class);
    }

    /**
     * @param  Builder<Screen>  $query
     * @return Builder<Screen>
     */
    public function scopeForWorkspace(Builder $query, Workspace|int $workspace): Builder
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $query->where('workspace_id', $id);
    }

    public function activeDevice(): ?ScreenDevice
    {
        return $this->devices()
            ->whereNull('revoked_at')
            ->orderByDesc('paired_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return 'connected'|'disconnected'
     */
    public function pairingState(): string
    {
        return ScreenPresence::pairingState($this->activeDevice());
    }

    /**
     * @return 'online'|'offline'
     */
    public function networkState(): string
    {
        return ScreenPresence::networkState($this->activeDevice());
    }

    /**
     * @return HasMany<ScreenHeartbeat, $this>
     */
    public function heartbeats(): HasMany
    {
        return $this->hasMany(ScreenHeartbeat::class);
    }

    public function activeDeployment(): ?Deployment
    {
        return $this->deployments()
            ->where('status', DeploymentStatus::Active)
            ->orderByDesc('deployed_at')
            ->orderByDesc('id')
            ->first();
    }
}
