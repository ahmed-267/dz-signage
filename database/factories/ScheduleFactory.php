<?php

namespace Database\Factories;

use App\Enums\ScheduleStatus;
use App\Models\Playlist;
use App\Models\PlaylistVersion;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Schedules\ScheduleDays;
use App\Support\Schedules\ScheduleDefaults;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->word().' Schedule',
            'description' => null,
            'playlist_id' => null,
            'playlist_version_id' => null,
            'timezone' => 'UTC',
            'start_date' => null,
            'end_date' => null,
            'start_time' => ScheduleDefaults::startTime(),
            'end_time' => ScheduleDefaults::endTime(),
            'days_of_week' => ScheduleDays::all(),
            'priority' => ScheduleDefaults::defaultPriority(),
            'status' => ScheduleStatus::Draft,
            'activated_at' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn () => [
            'workspace_id' => $workspace->id,
            'timezone' => $workspace->timezone,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => [
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Pins the playlist's published version, the way SaveSchedule does — a
     * schedule never follows a playlist to a later version on its own.
     */
    public function forPlaylist(Playlist $playlist, ?PlaylistVersion $version = null): static
    {
        return $this->state(fn () => [
            'playlist_id' => $playlist->id,
            'playlist_version_id' => $version !== null ? $version->id : $playlist->published_version_id,
        ]);
    }

    public function forScreens(Screen ...$screens): static
    {
        return $this->afterCreating(function (Schedule $schedule) use ($screens): void {
            $schedule->screens()->sync(array_map(static fn (Screen $screen): int => $screen->id, $screens));
        });
    }

    public function timezone(string $timezone): static
    {
        return $this->state(fn () => ['timezone' => $timezone]);
    }

    /**
     * A `HH:MM` wall-clock window in the schedule's own timezone. An end
     * earlier than the start crosses midnight.
     *
     * @param  list<int>|null  $days  ISO weekdays, 1 = Monday … 7 = Sunday.
     */
    public function window(string $startTime, string $endTime, ?array $days = null): static
    {
        return $this->state(fn () => [
            'start_time' => ScheduleDefaults::normalizeTime($startTime) ?? $startTime,
            'end_time' => ScheduleDefaults::normalizeTime($endTime) ?? $endTime,
            'days_of_week' => $days === null ? ScheduleDays::all() : ScheduleDays::normalize($days),
        ]);
    }

    /**
     * @param  list<int>  $days
     */
    public function onDays(array $days): static
    {
        return $this->state(fn () => [
            'days_of_week' => ScheduleDays::normalize($days),
        ]);
    }

    public function dates(?string $startDate, ?string $endDate): static
    {
        return $this->state(fn () => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function priority(int $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }

    /**
     * `$activatedAt` breaks ties between equal priorities, so tests that care
     * about precedence set it explicitly.
     */
    public function active(DateTimeInterface|string|null $activatedAt = null): static
    {
        return $this->state(fn () => [
            'status' => ScheduleStatus::Active,
            'activated_at' => $activatedAt ?? now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => ScheduleStatus::Paused,
            'activated_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ScheduleStatus::Archived,
        ]);
    }
}
