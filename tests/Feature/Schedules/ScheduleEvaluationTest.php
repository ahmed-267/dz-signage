<?php

use App\Enums\PlaylistStatus;
use App\Enums\WorkspaceRole;
use App\Models\Playlist;
use App\Models\PlaylistVersion;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Schedules\ScheduleDays;
use App\Support\Schedules\ScheduleEvaluator;
use Illuminate\Support\Carbon;

function evalWorkspace(): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, WorkspaceRole::Owner);

    return [$user, $workspace];
}

function evalScreen(User $user, Workspace $workspace, string $name = 'Lobby TV'): Screen
{
    return Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create(['name' => $name, 'orientation' => 'landscape']);
}

/**
 * Schedules here never need real playlist *items*: the evaluator decides
 * timing only. They do need a pinned published version, because resolution
 * ignores an active schedule that has no content to play.
 */
function evalPlaylist(User $user, Workspace $workspace): Playlist
{
    $playlist = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->landscape()
        ->create();

    $version = PlaylistVersion::factory()
        ->forPlaylist($playlist)
        ->published()
        ->create(['created_by' => $user->id]);

    $playlist->forceFill([
        'status' => PlaylistStatus::Published,
        'published_version_id' => $version->id,
        'published_at' => now(),
    ])->save();

    return $playlist;
}

/**
 * @param  list<int>|null  $days
 */
function evalSchedule(
    User $user,
    Workspace $workspace,
    Screen $screen,
    string $start,
    string $end,
    ?array $days = null,
    string $timezone = 'UTC',
    array $attributes = [],
): Schedule {
    return Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))
        ->forScreens($screen)
        ->timezone($timezone)
        ->window($start, $end, $days)
        ->active()
        ->create([
            'name' => $attributes['name'] ?? 'Window',
            ...$attributes,
        ]);
}

function evaluator(): ScheduleEvaluator
{
    return app(ScheduleEvaluator::class);
}

afterEach(function () {
    Carbon::setTestNow();
});

test('a schedule matches inside its window on a selected day only', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);

    // Monday 14 September 2026.
    $schedule = evalSchedule($user, $workspace, $screen, '09:00', '17:00', [ScheduleDays::MONDAY]);

    expect(evaluator()->matches($schedule, Carbon::parse('2026-09-14 12:00', 'UTC')))->toBeTrue()
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-14 08:59', 'UTC')))->toBeFalse()
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-14 17:00', 'UTC')))->toBeFalse()
        // Tuesday is not selected.
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-15 12:00', 'UTC')))->toBeFalse();
});

test('windows are inclusive of their start minute and exclusive of their end minute', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);

    $breakfast = evalSchedule($user, $workspace, $screen, '07:00', '12:00', null, 'UTC', ['name' => 'Breakfast']);
    $lunch = evalSchedule($user, $workspace, $screen, '12:00', '15:00', null, 'UTC', ['name' => 'Lunch']);

    $changeover = Carbon::parse('2026-09-14 12:00', 'UTC');

    expect(evaluator()->matches($breakfast, $changeover))->toBeFalse()
        ->and(evaluator()->matches($lunch, $changeover))->toBeTrue()
        ->and(evaluator()->matches($breakfast, $changeover->copy()->subMinute()))->toBeTrue()
        ->and(evaluator()->matches($lunch, $changeover->copy()->subMinute()))->toBeFalse();

    // Exactly one schedule owns the changeover instant.
    expect(evaluator()->resolveForScreen($screen, $changeover)->id)->toBe($lunch->id);
});

test('an overnight window belongs to the day it opened', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);

    // Friday nights only: 22:00 Friday through 02:00 Saturday.
    $schedule = evalSchedule($user, $workspace, $screen, '22:00', '02:00', [ScheduleDays::FRIDAY]);

    // Friday 18 September 2026 evening, and its Saturday morning tail.
    expect(evaluator()->matches($schedule, Carbon::parse('2026-09-18 23:30', 'UTC')))->toBeTrue()
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-19 01:30', 'UTC')))->toBeTrue()
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-19 02:00', 'UTC')))->toBeFalse()
        // Saturday evening is not a Friday, so nothing opens.
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-19 23:30', 'UTC')))->toBeFalse()
        // Friday 01:30 would belong to a Thursday window, which is not selected.
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-18 01:30', 'UTC')))->toBeFalse();
});

test('an overnight window reports the occurrence it opened on', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);

    $schedule = evalSchedule($user, $workspace, $screen, '22:00', '02:00', [ScheduleDays::FRIDAY]);

    $window = evaluator()->window($schedule, Carbon::parse('2026-09-19 01:00', 'UTC'));

    expect($window)->not->toBeNull()
        ->and($window->anchorDate->format('Y-m-d'))->toBe('2026-09-18')
        ->and($window->startsAtUtc()->format('Y-m-d H:i'))->toBe('2026-09-18 22:00')
        ->and($window->endsAtUtc()->format('Y-m-d H:i'))->toBe('2026-09-19 02:00');
});

test('matching uses the schedule timezone, not the server timezone', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);

    // Africa/Algiers is UTC+1 year round.
    $schedule = evalSchedule($user, $workspace, $screen, '09:00', '17:00', null, 'Africa/Algiers');

    expect(evaluator()->matches($schedule, Carbon::parse('2026-09-14 08:30', 'UTC')))->toBeTrue()
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-14 07:30', 'UTC')))->toBeFalse()
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-14 16:30', 'UTC')))->toBeFalse();

    $window = evaluator()->window($schedule, Carbon::parse('2026-09-14 08:30', 'UTC'));

    expect($window->startsAtUtc()->format('H:i'))->toBe('08:00')
        ->and($window->endsAtUtc()->format('H:i'))->toBe('16:00');
});

test('a schedule outside its date range never matches', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))
        ->forScreens($screen)
        ->timezone('UTC')
        ->window('09:00', '17:00')
        ->dates('2026-09-14', '2026-09-16')
        ->active()
        ->create(['name' => 'Festival Week']);

    expect(evaluator()->matches($schedule, Carbon::parse('2026-09-13 12:00', 'UTC')))->toBeFalse()
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-14 12:00', 'UTC')))->toBeTrue()
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-16 12:00', 'UTC')))->toBeTrue()
        ->and(evaluator()->matches($schedule, Carbon::parse('2026-09-17 12:00', 'UTC')))->toBeFalse();
});

test('an active schedule past its end date reads as Ended and stops matching', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))
        ->forScreens($screen)
        ->timezone('UTC')
        ->window('09:00', '17:00')
        ->dates(null, '2026-09-10')
        ->active()
        ->create(['name' => 'Summer Promo']);

    Carbon::setTestNow('2026-09-14 12:00:00');

    expect($schedule->hasEnded())->toBeTrue()
        ->and($schedule->displayStatus())->toBe('ended')
        ->and($schedule->displayStatusLabel())->toBe('Ended')
        ->and(evaluator()->matches($schedule, now()))->toBeFalse()
        ->and(evaluator()->resolveForScreen($screen, now()))->toBeNull();

    Carbon::setTestNow('2026-09-10 12:00:00');

    expect($schedule->hasEnded())->toBeFalse()
        ->and($schedule->displayStatus())->toBe('active')
        ->and(evaluator()->matches($schedule, now()))->toBeTrue();
});

test('only active schedules match — drafts, paused and archived never play', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);
    $at = Carbon::parse('2026-09-14 12:00', 'UTC');

    $draft = Schedule::factory()->forWorkspace($workspace)->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))->forScreens($screen)
        ->timezone('UTC')->window('09:00', '17:00')->create(['name' => 'Draft']);

    $paused = Schedule::factory()->forWorkspace($workspace)->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))->forScreens($screen)
        ->timezone('UTC')->window('09:00', '17:00')->paused()->create(['name' => 'Paused']);

    $archived = Schedule::factory()->forWorkspace($workspace)->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))->forScreens($screen)
        ->timezone('UTC')->window('09:00', '17:00')->archived()->create(['name' => 'Archived']);

    expect(evaluator()->matches($draft, $at))->toBeFalse()
        ->and(evaluator()->matches($paused, $at))->toBeFalse()
        ->and(evaluator()->matches($archived, $at))->toBeFalse()
        ->and(evaluator()->resolveForScreen($screen, $at))->toBeNull();

    // The window itself is fine — only the status keeps them off screen.
    expect(evaluator()->window($paused, $at))->not->toBeNull();
});

test('the highest priority schedule wins an overlap', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);
    $at = Carbon::parse('2026-09-14 12:00', 'UTC');

    $base = Schedule::factory()->forWorkspace($workspace)->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))->forScreens($screen)
        ->timezone('UTC')->window('09:00', '17:00')->priority(5)->active()
        ->create(['name' => 'All Day']);

    $promo = Schedule::factory()->forWorkspace($workspace)->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))->forScreens($screen)
        ->timezone('UTC')->window('11:00', '14:00')->priority(10)->active()
        ->create(['name' => 'Lunch Promo']);

    expect(evaluator()->resolveForScreen($screen, $at)->id)->toBe($promo->id)
        ->and($promo->outranks($base))->toBeTrue()
        ->and($base->outranks($promo))->toBeFalse();

    // Outside the promo window the base schedule takes over again.
    expect(evaluator()->resolveForScreen($screen, Carbon::parse('2026-09-14 15:00', 'UTC'))->id)
        ->toBe($base->id);
});

test('equal priorities break on the most recent activation, then the higher id', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);
    $at = Carbon::parse('2026-09-14 12:00', 'UTC');

    $older = Schedule::factory()->forWorkspace($workspace)->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))->forScreens($screen)
        ->timezone('UTC')->window('09:00', '17:00')->priority(6)
        ->active('2026-09-01 08:00:00')
        ->create(['name' => 'Older']);

    $newer = Schedule::factory()->forWorkspace($workspace)->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))->forScreens($screen)
        ->timezone('UTC')->window('09:00', '17:00')->priority(6)
        ->active('2026-09-05 08:00:00')
        ->create(['name' => 'Newer']);

    expect(evaluator()->resolveForScreen($screen, $at)->id)->toBe($newer->id)
        ->and($newer->outranks($older))->toBeTrue();

    // Identical activation instants fall back to the higher id.
    $newer->forceFill(['activated_at' => $older->activated_at])->save();

    expect(evaluator()->resolveForScreen($screen, $at)->id)->toBe($newer->id)
        ->and($newer->fresh()->outranks($older->fresh()))->toBeTrue()
        ->and($older->fresh()->outranks($newer->fresh()))->toBeFalse();
});

test('resolution ignores schedules for other screens and other workspaces', function () {
    [$user, $workspace] = evalWorkspace();
    [$otherUser, $otherWorkspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);
    $otherScreen = evalScreen($user, $workspace, 'Bar TV');
    $foreignScreen = evalScreen($otherUser, $otherWorkspace, 'Foreign TV');
    $at = Carbon::parse('2026-09-14 12:00', 'UTC');

    evalSchedule($user, $workspace, $otherScreen, '09:00', '17:00', null, 'UTC', ['name' => 'Bar Only']);
    evalSchedule($otherUser, $otherWorkspace, $foreignScreen, '09:00', '17:00', null, 'UTC', ['name' => 'Foreign']);

    expect(evaluator()->resolveForScreen($screen, $at))->toBeNull()
        ->and(evaluator()->resolveForScreen($otherScreen, $at)->name)->toBe('Bar Only');
});

test('a schedule with no selected days or a zero length window never plays', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);
    $at = Carbon::parse('2026-09-14 12:00', 'UTC');

    $noDays = Schedule::factory()->forWorkspace($workspace)->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))->forScreens($screen)
        ->timezone('UTC')->window('09:00', '17:00', [])->active()->create(['name' => 'No Days']);

    $zeroLength = Schedule::factory()->forWorkspace($workspace)->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))->forScreens($screen)
        ->timezone('UTC')->window('12:00', '12:00')->active()->create(['name' => 'Zero Length']);

    expect(evaluator()->matches($noDays, $at))->toBeFalse()
        ->and(evaluator()->matches($zeroLength, $at))->toBeFalse()
        ->and(evaluator()->nextWindow($noDays, $at))->toBeNull()
        ->and(evaluator()->nextWindow($zeroLength, $at))->toBeNull();
});

test('the next occurrence is the earliest upcoming start across active schedules', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);

    // Sunday 13 September 2026, 20:00 UTC.
    $at = Carbon::parse('2026-09-13 20:00', 'UTC');

    $weekday = evalSchedule(
        $user, $workspace, $screen, '09:00', '17:00',
        ScheduleDays::weekdays(), 'UTC', ['name' => 'Weekday Loop'],
    );

    $lateMonday = evalSchedule(
        $user, $workspace, $screen, '18:00', '20:00',
        [ScheduleDays::MONDAY], 'UTC', ['name' => 'Monday Evening'],
    );

    $next = evaluator()->findNextForScreen($screen, $at);

    expect($next)->not->toBeNull()
        ->and($next['schedule']->id)->toBe($weekday->id)
        ->and($next['starts_at']->format('Y-m-d H:i'))->toBe('2026-09-14 09:00')
        ->and($next['ends_at']->format('Y-m-d H:i'))->toBe('2026-09-14 17:00');

    // Once the weekday window is running, its next occurrence is tomorrow, so
    // Monday evening becomes the nearer upcoming start.
    $duringWeekday = evaluator()->findNextForScreen($screen, Carbon::parse('2026-09-14 12:00', 'UTC'));

    expect($duringWeekday['schedule']->id)->toBe($lateMonday->id)
        ->and($duringWeekday['starts_at']->format('Y-m-d H:i'))->toBe('2026-09-14 18:00');
});

test('a schedule whose window has passed for good has no next occurrence', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)->forPlaylist(evalPlaylist($user, $workspace))
        ->forScreens($screen)
        ->timezone('UTC')
        ->window('09:00', '17:00')
        ->dates(null, '2026-09-10')
        ->active()
        ->create(['name' => 'Finished']);

    expect(evaluator()->nextWindow($schedule, Carbon::parse('2026-09-14 12:00', 'UTC')))->toBeNull()
        ->and(evaluator()->findNextForScreen($screen, Carbon::parse('2026-09-14 12:00', 'UTC')))->toBeNull();
});

test('the evaluator uses a single instant even when the clock moves', function () {
    [$user, $workspace] = evalWorkspace();
    $screen = evalScreen($user, $workspace);

    $schedule = evalSchedule($user, $workspace, $screen, '09:00', '17:00');

    Carbon::setTestNow('2026-09-14 12:00:00');

    expect(evaluator()->matches($schedule, now()))->toBeTrue()
        ->and(evaluator()->resolveForScreen($screen)->id)->toBe($schedule->id);

    Carbon::setTestNow('2026-09-14 18:00:00');

    expect(evaluator()->matches($schedule, now()))->toBeFalse()
        ->and(evaluator()->resolveForScreen($screen))->toBeNull();
});
