<?php

namespace App\Support\Onboarding;

use App\Enums\WorkspaceRole;
use App\Models\Deployment;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Demo\ProductionDemoAccount;

/**
 * Product tour + checklist for new workspace Owners (and role-aware variants).
 *
 * Distinct from Business creation at `/onboarding`. Persistence lives on users.
 */
final class ProductOnboarding
{
    public const STEPS = 9;

    /**
     * @return array<string, mixed>
     */
    public function sharedPayload(User $user, ?Workspace $workspace, ?WorkspaceRole $role): array
    {
        if ($workspace === null || $role === null) {
            return $this->inactivePayload();
        }

        $checklist = $this->checklist($workspace);
        $completedAt = $user->onboarding_completed_at;
        $skippedAt = $user->onboarding_skipped_at;
        $startedAt = $user->onboarding_started_at;
        $completed = $completedAt !== null;
        $skipped = $skippedAt !== null;
        $step = max(1, min(self::STEPS, (int) ($user->onboarding_step ?? 1)));

        $canPair = in_array($role, [
            WorkspaceRole::Owner,
            WorkspaceRole::Admin,
            WorkspaceRole::LocationManager,
        ], true);
        $canPublish = in_array($role, [
            WorkspaceRole::Owner,
            WorkspaceRole::Admin,
            WorkspaceRole::ContentManager,
        ], true);

        // Replay is active when the latest start is after the last completion
        // (or there is no completion yet). Second-resolution columns can make
        // restart share the same timestamp as complete — treat step < STEPS
        // with equal timestamps as an in-progress replay.
        $replaying = $startedAt !== null
            && (
                $completedAt === null
                || $startedAt->greaterThan($completedAt)
                || ($startedAt->equalTo($completedAt) && $step < self::STEPS)
            );

        $active = ! $skipped && $replaying;

        $isDemoOrMature = $this->isDemoUser($user) || $this->isMatureWorkspace($workspace);

        $autoStart = ! $isDemoOrMature
            && ! $completed
            && ! $skipped
            && $role === WorkspaceRole::Owner
            && $startedAt === null
            && ! $checklist['has_screen']
            && ! $checklist['has_tv']
            && ! $checklist['has_publish'];

        return [
            // Auto-start eligibility (new Owners only). Manual replay always allowed.
            'eligible' => ! $isDemoOrMature,
            'can_replay' => true,
            'active' => $active,
            'auto_start' => $autoStart,
            'completed' => $completed && ! $replaying,
            'skipped' => $skipped,
            'step' => $step,
            'total_steps' => self::STEPS,
            'can_pair' => $canPair,
            'can_publish' => $canPublish,
            'checklist' => $checklist,
            'show_checklist' => ! $isDemoOrMature
                && ! $skipped
                && (! $completed || $replaying)
                && ! $checklist['all_done'],
            'reason' => $isDemoOrMature ? 'mature_or_demo' : null,
        ];
    }

    /**
     * @return array{
     *     has_business: bool,
     *     has_screen: bool,
     *     has_tv: bool,
     *     has_publish: bool,
     *     complete_count: int,
     *     total: int,
     *     all_done: bool
     * }
     */
    public function checklist(Workspace $workspace): array
    {
        $hasScreen = ScreenDesign::query()
            ->where('workspace_id', $workspace->id)
            ->exists();
        $hasTv = Screen::query()
            ->where('workspace_id', $workspace->id)
            ->whereHas('devices', fn ($q) => $q->whereNull('revoked_at'))
            ->exists();
        $hasPublish = Deployment::query()
            ->where('workspace_id', $workspace->id)
            ->exists();

        $items = [
            'has_business' => true,
            'has_screen' => $hasScreen,
            'has_tv' => $hasTv,
            'has_publish' => $hasPublish,
        ];
        $complete = count(array_filter($items));

        return [
            ...$items,
            'complete_count' => $complete,
            'total' => 4,
            'all_done' => $complete >= 4,
        ];
    }

    public function start(User $user): void
    {
        if ($user->onboarding_completed_at !== null) {
            return;
        }

        $user->forceFill([
            'onboarding_started_at' => $user->onboarding_started_at ?? now(),
            'onboarding_skipped_at' => null,
            'onboarding_step' => max(1, (int) ($user->onboarding_step ?? 1)),
        ])->save();
    }

    public function advance(User $user, int $step): void
    {
        $user->forceFill([
            'onboarding_started_at' => $user->onboarding_started_at ?? now(),
            'onboarding_step' => max(1, min(self::STEPS, $step)),
            'onboarding_skipped_at' => null,
        ])->save();
    }

    public function complete(User $user): void
    {
        $user->forceFill([
            'onboarding_completed_at' => now(),
            'onboarding_step' => self::STEPS,
            'onboarding_skipped_at' => null,
            'onboarding_started_at' => $user->onboarding_started_at ?? now(),
        ])->save();
    }

    public function skip(User $user): void
    {
        $user->forceFill([
            'onboarding_skipped_at' => now(),
            'onboarding_started_at' => $user->onboarding_started_at ?? now(),
        ])->save();
    }

    /**
     * Manual replay from Help & Support. Keeps historical `onboarding_completed_at`
     * and does not create Business / Screen / TV data.
     */
    public function restart(User $user): void
    {
        $user->forceFill([
            'onboarding_started_at' => now(),
            'onboarding_skipped_at' => null,
            'onboarding_step' => 1,
        ])->save();
    }

    private function isDemoUser(User $user): bool
    {
        return strcasecmp($user->email, ProductionDemoAccount::email()) === 0;
    }

    private function isMatureWorkspace(Workspace $workspace): bool
    {
        // Already has content pipeline artifacts → do not force the tour.
        $designs = ScreenDesign::query()->where('workspace_id', $workspace->id)->count();
        $tvs = Screen::query()->where('workspace_id', $workspace->id)->count();
        $deployments = Deployment::query()->where('workspace_id', $workspace->id)->count();

        return ($designs + $tvs + $deployments) >= 3;
    }

    /**
     * @return array<string, mixed>
     */
    private function inactivePayload(): array
    {
        return [
            'eligible' => false,
            'can_replay' => false,
            'active' => false,
            'auto_start' => false,
            'completed' => true,
            'skipped' => false,
            'step' => self::STEPS,
            'total_steps' => self::STEPS,
            'can_pair' => false,
            'can_publish' => false,
            'checklist' => null,
            'show_checklist' => false,
            'reason' => null,
        ];
    }
}
