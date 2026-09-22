<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use App\Support\Schedules\ScheduleDays;
use Carbon\CarbonInterface;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A named timing window that plays a pinned published PlaylistVersion on a set
 * of Screens. Schedules decide *when* content plays; Deployments (Publish to
 * Screen) decide the always-on fallback.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string|null $description
 * @property int|null $playlist_id
 * @property int|null $playlist_version_id
 * @property string $timezone
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string $start_time
 * @property string $end_time
 * @property list<int> $days_of_week
 * @property int $priority
 * @property ScheduleStatus $status
 * @property Carbon|null $activated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'playlist_id',
        'playlist_version_id',
        'timezone',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'days_of_week',
        'priority',
        'status',
        'activated_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScheduleStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'days_of_week' => 'array',
            'priority' => 'integer',
            'activated_at' => 'datetime',
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
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return BelongsTo<PlaylistVersion, $this>
     */
    public function playlistVersion(): BelongsTo
    {
        return $this->belongsTo(PlaylistVersion::class);
    }

    /**
     * @return BelongsToMany<Screen, $this>
     */
    public function screens(): BelongsToMany
    {
        return $this->belongsToMany(Screen::class, 'schedule_screen')->withTimestamps();
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
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeForWorkspace(Builder $query, Workspace|int $workspace): Builder
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $query->where('workspace_id', $id);
    }

    /**
     * Highest priority wins. Ties break on the most recent activation, then on
     * the higher id, so resolution is always deterministic.
     *
     * `ScheduleEvaluator` orders the same way in SQL — keep the two in step.
     *
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeOrderByPrecedence(Builder $query): Builder
    {
        return $query
            ->orderByDesc('priority')
            ->orderByRaw('activated_at desc nulls last')
            ->orderByDesc('id');
    }

    /**
     * Whether this schedule beats `$other` when both windows match at once.
     * Single source of truth for the precedence rule described above.
     */
    public function outranks(self $other): bool
    {
        if ($this->priority !== $other->priority) {
            return $this->priority > $other->priority;
        }

        $mine = $this->activated_at?->getTimestamp();
        $theirs = $other->activated_at?->getTimestamp();

        if ($mine !== $theirs) {
            return ($mine ?? PHP_INT_MIN) > ($theirs ?? PHP_INT_MIN);
        }

        return $this->id > $other->id;
    }

    /**
     * @return list<int>
     */
    public function days(): array
    {
        return ScheduleDays::normalize($this->days_of_week);
    }

    /**
     * The window crosses midnight when it ends earlier in the day than it
     * starts (for example 22:00 → 02:00).
     */
    public function crossesMidnight(): bool
    {
        return $this->startMinutes() > $this->endMinutes();
    }

    public function startMinutes(): int
    {
        return self::minutesFromTime($this->start_time);
    }

    public function endMinutes(): int
    {
        return self::minutesFromTime($this->end_time);
    }

    /**
     * Length of one occurrence, accounting for windows that cross midnight.
     */
    public function durationMinutes(): int
    {
        $start = $this->startMinutes();
        $end = $this->endMinutes();

        return $end > $start ? $end - $start : (1440 - $start) + $end;
    }

    public function localNow(?CarbonInterface $at = null): Carbon
    {
        return Carbon::instance($at ?? now())->setTimezone($this->timezone);
    }

    /**
     * Derived "Ended": an active schedule whose end date is already in the
     * past in its own timezone. Distinct from a Screen being operationally
     * Active, and never stored.
     */
    public function hasEnded(?CarbonInterface $at = null): bool
    {
        if ($this->status !== ScheduleStatus::Active || $this->end_date === null) {
            return false;
        }

        // Calendar-date comparison: the local date and the stored date column
        // are in different timezones, so instants must not be compared.
        return $this->localNow($at)->format('Y-m-d') > $this->end_date->format('Y-m-d');
    }

    /**
     * Status for display: the stored status, or `ended` when an active
     * schedule has run past its end date.
     */
    public function displayStatus(?CarbonInterface $at = null): string
    {
        return $this->hasEnded($at) ? 'ended' : $this->status->value;
    }

    public function displayStatusLabel(?CarbonInterface $at = null): string
    {
        return $this->hasEnded($at) ? 'Ended' : $this->status->label();
    }

    /**
     * `HH:MM` minutes since local midnight.
     */
    public static function minutesFromTime(?string $time): int
    {
        if ($time === null || $time === '') {
            return 0;
        }

        $parts = explode(':', $time);
        $hours = (int) $parts[0];
        $minutes = (int) ($parts[1] ?? 0);

        return ($hours * 60) + $minutes;
    }
}
