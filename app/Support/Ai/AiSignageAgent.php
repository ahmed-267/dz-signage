<?php

namespace App\Support\Ai;

use App\Actions\Playlists\CreatePlaylist;
use App\Actions\Playlists\PublishPlaylist;
use App\Actions\Playlists\PublishPlaylistToScreens;
use App\Actions\Playlists\SavePlaylistDraft;
use App\Actions\Schedules\ActivateSchedule;
use App\Actions\Schedules\CreateSchedule;
use App\Actions\ScreenDesigns\PublishScreenDesign;
use App\Enums\AiGenerationStatus;
use App\Enums\AiGenerationType;
use App\Enums\ScheduleStatus;
use App\Models\AiGeneration;
use App\Models\Location;
use App\Models\Playlist;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Platform\AuditLogger;
use App\Support\Playlists\PlaylistDefaults;
use App\Support\Schedules\ScheduleDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Deterministic signage-building agent: propose a plan, then confirm drafts.
 * Never activates schedules or deploys to screens unless explicit options are set.
 */
final class AiSignageAgent
{
    public function __construct(
        private readonly AiContentService $ai,
        private readonly CreatePlaylist $createPlaylist,
        private readonly SavePlaylistDraft $savePlaylistDraft,
        private readonly PublishPlaylist $publishPlaylist,
        private readonly PublishPlaylistToScreens $publishPlaylistToScreens,
        private readonly CreateSchedule $createSchedule,
        private readonly ActivateSchedule $activateSchedule,
        private readonly PublishScreenDesign $publishScreenDesign,
    ) {}

    /**
     * @param  array{
     *     orientation?: string,
     *     purpose?: string,
     *     style?: string,
     *     name?: string|null,
     *     preferred_intent?: string|null,
     *     screen_hints?: list<string>|null,
     *     activate?: bool,
     *     deploy?: bool,
     *     generate_matching_image?: bool,
     *     idempotency_key?: string|null
     * }  $options
     * @return array<string, mixed>
     */
    public function propose(User $user, Workspace $workspace, string $prompt, array $options = []): array
    {
        if (! AiAvailability::isConfigured()) {
            throw AiException::unavailable();
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            throw ValidationException::withMessages(['prompt' => 'A prompt is required.']);
        }

        $max = (int) config('ai.limits.prompt_max', 2000);
        if (mb_strlen($prompt) > $max) {
            throw ValidationException::withMessages(['prompt' => "Prompt must be {$max} characters or fewer."]);
        }

        $intent = $this->classifyIntent($prompt, $options['preferred_intent'] ?? null);
        $flags = $this->detectIntentFlags($prompt);
        $orientation = (string) ($options['orientation'] ?? config('ai.agent.default_orientation', 'landscape'));
        if (! in_array($orientation, ['landscape', 'portrait'], true)) {
            $orientation = 'landscape';
        }

        $purpose = $this->inferPurpose($prompt, (string) ($options['purpose'] ?? config('ai.agent.default_purpose', 'promotion')));
        $style = (string) ($options['style'] ?? config('ai.agent.default_style', 'professional'));
        $name = trim((string) ($options['name'] ?? '')) !== ''
            ? (string) $options['name']
            : $this->suggestedName($intent, $prompt);

        $screens = $this->resolveScreens($workspace, $prompt, $options['screen_hints'] ?? null);
        $ambiguous = $this->ambiguousScreenMatches($workspace, $prompt);
        $warnings = [];
        if ($ambiguous !== []) {
            $screens = [];
            $names = implode(', ', array_map(static fn (array $s): string => $s['name'], $ambiguous));
            $warnings[] = 'More than one TV matches that name ('.$names.'). Choose the exact TV before confirming.';
        } elseif ($this->wantsScreens($prompt, $intent) && $screens === []) {
            $warnings[] = 'No matching screens were found by name or location. The schedule will be created without screens until you assign them.';
        }

        $requirements = $this->extractRequirements($prompt);

        $steps = $this->buildSteps($intent, $prompt, [
            'orientation' => $orientation,
            'purpose' => $purpose,
            'style' => $style,
            'name' => $name,
            'screens' => $screens,
            'generate_matching_image' => (bool) ($options['generate_matching_image'] ?? false),
            'flags' => $flags,
            'preferred_intent' => $options['preferred_intent'] ?? null,
            'requirements' => $requirements,
        ]);

        $willActivate = (bool) ($options['activate'] ?? false);
        $willDeploy = (bool) ($options['deploy'] ?? false);

        $proposal = [
            'intent' => $intent,
            'prompt' => $prompt,
            'summary' => $this->summaryFor($intent, $steps, $screens),
            'steps' => $steps,
            'resolved_screens' => array_map(static fn (array $s): array => [
                'id' => $s['id'],
                'name' => $s['name'],
                'location' => $s['location'],
            ], $screens),
            'warnings' => $warnings,
            'requirements' => $requirements,
            'ambiguous_screens' => $ambiguous,
            'options' => [
                'orientation' => $orientation,
                'purpose' => $purpose,
                'style' => $style,
                'activate' => $willActivate,
                'deploy' => $willDeploy,
                'generate_matching_image' => (bool) ($options['generate_matching_image'] ?? false),
            ],
            'requires_confirmation' => true,
            'will_activate' => $willActivate,
            'will_deploy' => $willDeploy,
        ];

        $generation = AiGeneration::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'type' => AiGenerationType::Agent,
            'status' => AiGenerationStatus::Completed,
            'provider' => 'agent',
            'model' => 'heuristic-v1',
            'prompt' => $prompt,
            'options' => [
                'intent' => $intent,
                'preferred_intent' => $options['preferred_intent'] ?? null,
                'idempotency_key' => $options['idempotency_key'] ?? null,
            ],
            'output' => [
                'proposal' => $proposal,
                'confirmed' => false,
            ],
            'idempotency_key' => filled($options['idempotency_key'] ?? null)
                ? mb_substr((string) $options['idempotency_key'], 0, 64)
                : null,
            'completed_at' => now(),
        ]);

        return [
            'proposal_id' => $generation->id,
            ...$proposal,
        ];
    }

    /**
     * @param  array{
     *     activate?: bool,
     *     deploy?: bool,
     *     confirm?: bool,
     *     confirmed?: bool
     * }  $options
     * @return array<string, mixed>
     */
    public function confirm(User $user, Workspace $workspace, AiGeneration $proposal, array $options = []): array
    {
        if ((int) $proposal->workspace_id !== (int) $workspace->id) {
            abort(404);
        }

        if ($proposal->type !== AiGenerationType::Agent) {
            throw ValidationException::withMessages(['proposal_id' => 'Invalid AI agent proposal.']);
        }

        $confirmed = (bool) ($options['confirm'] ?? $options['confirmed'] ?? false);
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm' => 'Set confirm=true to execute this proposal.',
            ]);
        }

        $plan = is_array($proposal->output['proposal'] ?? null) ? $proposal->output['proposal'] : null;
        if ($plan === null) {
            throw ValidationException::withMessages(['proposal_id' => 'This proposal has no plan to execute.']);
        }

        if (! empty($proposal->output['confirmed'])) {
            return is_array($proposal->output['result'] ?? null)
                ? $proposal->output['result']
                : ['proposal_id' => $proposal->id, 'already_confirmed' => true];
        }

        if (! empty($plan['ambiguous_screens'])) {
            throw ValidationException::withMessages([
                'screens' => 'Choose an exact TV before confirming this plan.',
            ]);
        }

        // confirm=true already required above. activate/deploy only when the caller
        // explicitly sets those options — never inferred from the prompt alone.
        $activate = (bool) ($options['activate'] ?? false);
        $deploy = (bool) ($options['deploy'] ?? false);

        $result = DB::transaction(function () use ($user, $workspace, $plan, $activate, $deploy) {
            return $this->executeSteps($user, $workspace, $plan, $activate, $deploy);
        });

        $output = $proposal->output ?? [];
        $output['confirmed'] = true;
        $output['result'] = $result;
        $output['activated'] = $activate;
        $output['deployed'] = $deploy;

        $proposal->forceFill([
            'output' => $output,
            'completed_at' => now(),
        ])->save();

        AuditLogger::record(
            $user,
            'ai.agent_confirmed',
            'ai_generation',
            $proposal->id,
            $workspace->id,
            [
                'intent' => $plan['intent'] ?? null,
                'activate' => $activate,
                'deploy' => $deploy,
            ],
        );

        return $result;
    }

    /**
     * Keyword / heuristic classifier — reliable for tests; LLM refine can come later.
     */
    public function classifyIntent(string $prompt, ?string $preferred = null): string
    {
        $allowed = config('ai.agent.intents', ['design', 'playlist', 'schedule', 'compound']);
        $detected = $this->detectIntentFlags($prompt);

        // Surface preference wins unless the prompt explicitly asks to schedule.
        if ($preferred !== null && in_array($preferred, $allowed, true)) {
            if ($preferred !== 'schedule' && $detected['schedule'] && ($detected['playlist'] || $preferred === 'playlist')) {
                return 'compound';
            }

            return $preferred;
        }

        if ($detected['playlist'] && $detected['schedule']) {
            return 'compound';
        }

        if ($detected['playlist']) {
            return 'playlist';
        }
        if ($detected['schedule']) {
            return 'schedule';
        }

        return 'design';
    }

    /**
     * @return array{design: bool, playlist: bool, schedule: bool}
     */
    private function detectIntentFlags(string $prompt): array
    {
        $lower = mb_strtolower($prompt);
        $keywords = config('ai.agent.intent_keywords', []);

        $hit = static function (array $words) use ($lower): bool {
            foreach ($words as $word) {
                if ($word !== '' && str_contains($lower, mb_strtolower((string) $word))) {
                    return true;
                }
            }

            return false;
        };

        return [
            'design' => $hit($keywords['design'] ?? []),
            'playlist' => $hit($keywords['playlist'] ?? []),
            'schedule' => $hit($keywords['schedule'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    private function buildSteps(string $intent, string $prompt, array $context): array
    {
        $steps = [];
        $flags = is_array($context['flags'] ?? null) ? $context['flags'] : [
            'design' => true,
            'playlist' => false,
            'schedule' => false,
        ];

        $wantsPlaylist = $intent === 'playlist'
            || $intent === 'compound'
            || ($intent === 'schedule' && ! $this->mentionsExistingPlaylist($prompt));

        $wantsSchedule = $intent === 'schedule'
            || ($intent === 'compound' && ! empty($flags['schedule']));

        // Compound without an explicit schedule keyword still builds design + playlist.
        if ($intent === 'compound' && empty($flags['schedule']) && empty($flags['playlist'])) {
            $wantsPlaylist = true;
        }

        $requirements = is_array($context['requirements'] ?? null) ? $context['requirements'] : $this->extractRequirements($prompt);
        $designCount = max(1, min(6, (int) ($requirements['design_count'] ?? 1)));
        $itemDuration = (int) ($requirements['duration_seconds'] ?? config(
            'ai.agent.default_playlist_item_duration',
            PlaylistDefaults::durationSeconds(),
        ));
        $loopCount = max(1, min(99, (int) ($requirements['loop_count'] ?? 1)));

        $needsDesign = $intent === 'design'
            || $wantsPlaylist
            || ($wantsSchedule && ! $this->mentionsExistingPlaylist($prompt));

        $designIndexes = [];
        if ($needsDesign) {
            $count = $wantsPlaylist || $wantsSchedule ? $designCount : max(1, $designCount);
            for ($i = 0; $i < $count; $i++) {
                $label = $count > 1 ? ' Design '.($i + 1) : ' Design';
                $steps[] = [
                    'type' => 'create_design',
                    'index' => count($steps),
                    'name' => $context['name'].$label,
                    'prompt' => $prompt,
                    'purpose' => $context['purpose'],
                    'style' => $context['style'],
                    'orientation' => $context['orientation'],
                    'generate_matching_image' => (bool) ($context['generate_matching_image'] ?? false),
                    'publish_design' => $wantsPlaylist || $wantsSchedule,
                ];
                $designIndexes[] = count($steps) - 1;
            }
        }

        if ($wantsPlaylist) {
            $steps[] = [
                'type' => 'create_playlist',
                'index' => count($steps),
                'name' => $context['name'].' Playlist',
                'orientation' => $context['orientation'],
                'design_step_indexes' => $designIndexes !== [] ? $designIndexes : [0],
                'duration_seconds' => $itemDuration,
                'loop_count' => $loopCount,
                'publish_playlist' => $wantsSchedule,
            ];
        }

        if ($wantsSchedule) {
            $screenIds = array_map(static fn (array $s): int => (int) $s['id'], $context['screens'] ?? []);
            $timing = $this->inferScheduleTiming($prompt);

            $steps[] = [
                'type' => 'create_schedule',
                'index' => count($steps),
                'name' => $context['name'].' Schedule',
                'playlist_step_index' => $this->findStepIndex($steps, 'create_playlist'),
                'screen_ids' => $screenIds,
                'screen_names' => array_map(static fn (array $s): string => (string) $s['name'], $context['screens'] ?? []),
                'timezone' => null,
                'start_date' => $timing['start_date'],
                'end_date' => $timing['end_date'],
                'start_time' => $timing['start_time'],
                'end_time' => $timing['end_time'],
                'days_of_week' => $timing['days_of_week'],
                'status' => ScheduleStatus::Draft->value,
                'activate' => false,
            ];
        }

        foreach ($steps as $i => &$step) {
            $step['index'] = $i;
        }
        unset($step);

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function executeSteps(
        User $user,
        Workspace $workspace,
        array $plan,
        bool $activate,
        bool $deploy,
    ): array {
        /** @var list<array<string, mixed>> $steps */
        $steps = [];
        foreach (is_array($plan['steps'] ?? null) ? $plan['steps'] : [] as $step) {
            if (is_array($step)) {
                $steps[] = $step;
            }
        }
        $createdDesigns = [];
        $createdPlaylists = [];
        $createdSchedules = [];
        $designByStep = [];
        $playlistByStep = [];

        foreach ($steps as $step) {
            $type = (string) ($step['type'] ?? '');

            if ($type === 'create_design') {
                $result = $this->ai->generateDesign($user, $workspace, (string) $step['prompt'], [
                    'orientation' => (string) ($step['orientation'] ?? 'landscape'),
                    'purpose' => (string) ($step['purpose'] ?? 'promotion'),
                    'style' => (string) ($step['style'] ?? 'professional'),
                    'name' => (string) ($step['name'] ?? 'AI Design'),
                    'generate_matching_image' => (bool) ($step['generate_matching_image'] ?? false),
                    'variant_count' => 1,
                    'prefer_existing_media' => true,
                    'prefer_templates' => true,
                ]);

                $design = $result['design'] ?? null;
                if (! $design instanceof ScreenDesign) {
                    throw ValidationException::withMessages([
                        'design' => 'AI design generation did not return a Screen Design.',
                    ]);
                }

                if (! empty($step['publish_design'])) {
                    $design = $this->publishScreenDesign->handle($user, $design);
                }

                $designByStep[(int) $step['index']] = $design;
                $createdDesigns[] = [
                    'id' => $design->id,
                    'name' => $design->name,
                    'status' => $design->status->value,
                    'edit_url' => route('app.screen_designs.edit', $design),
                ];
            }

            if ($type === 'create_playlist') {
                $designIndexes = is_array($step['design_step_indexes'] ?? null)
                    ? $step['design_step_indexes']
                    : [0];
                $items = [];
                foreach ($designIndexes as $designIndex) {
                    $design = $designByStep[(int) $designIndex] ?? null;
                    if ($design === null) {
                        continue;
                    }
                    if ($design->published_version_id === null) {
                        $design = $this->publishScreenDesign->handle($user, $design);
                        $designByStep[(int) $designIndex] = $design;
                    }
                    $items[] = [
                        'screen_design_id' => $design->id,
                        'duration_seconds' => (int) ($step['duration_seconds'] ?? PlaylistDefaults::durationSeconds()),
                        'loop_count' => max(1, (int) ($step['loop_count'] ?? 1)),
                    ];
                }

                if ($items === []) {
                    throw ValidationException::withMessages([
                        'playlist' => 'Playlist steps require at least one design.',
                    ]);
                }

                $playlist = $this->createPlaylist->handle(
                    $user,
                    $workspace,
                    (string) ($step['name'] ?? 'AI Playlist'),
                    null,
                    null,
                );

                $playlist = $this->savePlaylistDraft->handle($user, $playlist, [
                    'name' => (string) ($step['name'] ?? $playlist->name),
                    'orientation' => (string) ($step['orientation'] ?? 'landscape'),
                    'items' => $items,
                ]);

                $shouldPublishPlaylist = ! empty($step['publish_playlist']) || $activate || $deploy
                    || $this->planHasSchedule($steps);

                if ($shouldPublishPlaylist) {
                    $playlist = $this->publishPlaylist->handle($user, $playlist);
                }

                if ($deploy) {
                    $screenIds = $this->screenIdsFromPlan($plan);
                    if ($screenIds !== []) {
                        $this->publishPlaylistToScreens->handle($user, $workspace, $playlist, $screenIds);
                    }
                }

                $playlistByStep[(int) $step['index']] = $playlist;
                $createdPlaylists[] = [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'status' => $playlist->status->value,
                    'edit_url' => route('app.playlists.edit', $playlist),
                ];
            }

            if ($type === 'create_schedule') {
                $playlistIndex = $step['playlist_step_index'] ?? null;
                $playlist = $playlistIndex !== null
                    ? ($playlistByStep[(int) $playlistIndex] ?? null)
                    : null;

                if ($playlist !== null && $playlist->published_version_id === null) {
                    $playlist = $this->publishPlaylist->handle($user, $playlist);
                    $playlistByStep[(int) $playlistIndex] = $playlist;
                }

                $data = [
                    'name' => (string) ($step['name'] ?? 'AI Schedule'),
                    'start_time' => $this->hhmm((string) ($step['start_time'] ?? ScheduleDefaults::startTime())),
                    'end_time' => $this->hhmm((string) ($step['end_time'] ?? ScheduleDefaults::endTime())),
                    'days_of_week' => is_array($step['days_of_week'] ?? null)
                        ? $step['days_of_week']
                        : ScheduleDefaults::daysOfWeek(),
                    'screen_ids' => is_array($step['screen_ids'] ?? null) ? $step['screen_ids'] : [],
                    'timezone' => $workspace->timezone,
                ];

                if (! empty($step['start_date'])) {
                    $data['start_date'] = $step['start_date'];
                }
                if (! empty($step['end_date'])) {
                    $data['end_date'] = $step['end_date'];
                }
                if ($playlist !== null) {
                    $data['playlist_id'] = $playlist->id;
                }

                $schedule = $this->createSchedule->handle($user, $workspace, $data);

                if ($activate) {
                    $schedule = $this->activateSchedule->handle($user, $schedule);
                }

                $createdSchedules[] = [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'status' => $schedule->status->value,
                    'edit_url' => route('app.schedules.edit', $schedule),
                ];
            }
        }

        return [
            'intent' => $plan['intent'] ?? null,
            'designs' => $createdDesigns,
            'playlists' => $createdPlaylists,
            'schedules' => $createdSchedules,
            'activated' => $activate,
            'deployed' => $deploy,
        ];
    }

    /**
     * @param  list<string>|null  $hints
     * @return list<array{id: int, name: string, location: string|null}>
     */
    private function resolveScreens(Workspace $workspace, string $prompt, ?array $hints): array
    {
        $candidates = [];
        foreach ($hints ?? [] as $hint) {
            $hint = trim((string) $hint);
            if ($hint !== '') {
                $candidates[] = mb_strtolower($hint);
            }
        }

        // Quoted names: "Lobby TV"
        if (preg_match_all('/["“]([^"”]+)["”]/u', $prompt, $quoted) > 0) {
            foreach ($quoted[1] as $q) {
                $candidates[] = mb_strtolower(trim($q));
            }
        }

        // Patterns: on/at/for <Name>, screen/TV <Name>
        if (preg_match_all('/\b(?:on|at|for|screen|tv)\s+([A-Za-z0-9][\w\s\-]{1,40})/iu', $prompt, $matches) > 0) {
            foreach ($matches[1] as $m) {
                $candidates[] = mb_strtolower(trim($m));
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        $resolved = [];
        $screens = Screen::query()
            ->forWorkspace($workspace)
            ->with('location')
            ->get()
            ->sortByDesc(fn (Screen $screen): int => mb_strlen($screen->name));

        foreach ($screens as $screen) {
            $name = mb_strtolower($screen->name);
            if ($name === '') {
                continue;
            }

            $namedInPrompt = preg_match('/\b'.preg_quote($name, '/').'\b/iu', $prompt) === 1;
            $hinted = false;
            foreach ($candidates as $candidate) {
                if ($candidate === $name) {
                    $hinted = true;
                    break;
                }
            }

            if ($namedInPrompt || $hinted) {
                $resolved[$screen->id] = [
                    'id' => $screen->id,
                    'name' => $screen->name,
                    'location' => $screen->location?->name,
                ];
            }
        }

        return array_values($resolved);
    }

    /**
     * A short phrase such as "Lobby TV" that is contained in more than one TV name.
     *
     * @return list<array{id: int, name: string}>
     */
    private function ambiguousScreenMatches(Workspace $workspace, string $prompt): array
    {
        if (preg_match('/\b(?:on|at)\s+([A-Za-z0-9][\w\s\-]{1,40}?)(?=\s+(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday|from|between|\d{1,2}(?::\d{2})?\s*(?:am|pm))|$)/iu', $prompt, $match) !== 1) {
            return [];
        }

        $phrase = mb_strtolower(trim($match[1]));
        $phrase = trim((string) preg_replace('/\s+(and|then)\s+.*$/iu', '', $phrase));
        if ($phrase === '' || mb_strlen($phrase) < 3) {
            return [];
        }

        $screens = Screen::query()->forWorkspace($workspace)->get();
        $exact = $screens->first(fn (Screen $screen): bool => mb_strtolower($screen->name) === $phrase);
        if ($exact !== null) {
            return [];
        }

        $contains = $screens->filter(function (Screen $screen) use ($phrase): bool {
            return str_contains(mb_strtolower($screen->name), $phrase);
        })->values();

        if ($contains->count() < 2) {
            return [];
        }

        return array_values($contains->map(fn (Screen $screen): array => [
            'id' => $screen->id,
            'name' => $screen->name,
        ])->all());
    }

    /**
     * @return array{design_count: int, duration_seconds: int|null, loop_count: int}
     */
    private function extractRequirements(string $prompt): array
    {
        $designCount = 1;
        if (preg_match('/\b(\d+)\s+(?:screen\s+)?designs?\b/i', $prompt, $m) === 1
            || preg_match('/\b(\d+)\s+(?:football\s+)?screens?\b/i', $prompt, $m) === 1
        ) {
            $designCount = max(1, min(6, (int) $m[1]));
        }

        $duration = null;
        if (preg_match('/\b(\d+)\s*(?:seconds|second|secs|sec)\b/i', $prompt, $m) === 1) {
            $duration = max(1, min(3600, (int) $m[1]));
        }

        $loops = 1;
        if (preg_match('/\b(\d+)\s*(?:loops|loop|times)\b/i', $prompt, $m) === 1) {
            $loops = max(1, min(99, (int) $m[1]));
        } elseif (preg_match('/\bonce\b/i', $prompt) === 1) {
            $loops = 1;
        }

        return [
            'design_count' => $designCount,
            'duration_seconds' => $duration,
            'loop_count' => $loops,
        ];
    }

    private function wantsScreens(string $prompt, string $intent): bool
    {
        if (! in_array($intent, ['schedule', 'compound'], true)) {
            return false;
        }

        $lower = mb_strtolower($prompt);

        return str_contains($lower, 'screen')
            || str_contains($lower, ' tv')
            || str_contains($lower, 'on ')
            || str_contains($lower, 'at ')
            || str_contains($lower, 'lobby')
            || str_contains($lower, 'location');
    }

    private function mentionsExistingPlaylist(string $prompt): bool
    {
        return (bool) preg_match('/\b(existing|current|my)\s+playlist\b/i', $prompt);
    }

    /**
     * @return array{
     *     start_date: string|null,
     *     end_date: string|null,
     *     start_time: string,
     *     end_time: string,
     *     days_of_week: list<int>
     * }
     */
    private function inferScheduleTiming(string $prompt): array
    {
        $lower = mb_strtolower($prompt);
        $days = ScheduleDefaults::daysOfWeek();

        $dayMap = [
            'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
            'friday' => 5, 'saturday' => 6, 'sunday' => 7,
        ];

        $foundDays = [];
        foreach ($dayMap as $label => $num) {
            if (str_contains($lower, $label) || str_contains($lower, mb_substr($label, 0, 3))) {
                $foundDays[] = $num;
            }
        }

        if (str_contains($lower, 'weekday')) {
            $foundDays = [1, 2, 3, 4, 5];
        } elseif (str_contains($lower, 'weekend')) {
            $foundDays = [6, 7];
        } elseif (str_contains($lower, 'daily') || str_contains($lower, 'every day')) {
            $foundDays = [1, 2, 3, 4, 5, 6, 7];
        }

        if ($foundDays !== []) {
            $days = array_values(array_unique($foundDays));
            sort($days);
        }

        $startTime = ScheduleDefaults::startTime();
        $endTime = ScheduleDefaults::endTime();

        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s*(?:-|to|until|–|—)\s*midnight\b/i', $prompt, $m) === 1) {
            $startMinute = isset($m[2]) ? (int) $m[2] : 0;
            $startMeridiem = isset($m[3]) ? $m[3] : null;
            $startTime = $this->normalizeClock((int) $m[1], $startMinute, $startMeridiem);
            $endTime = '00:00:00';
        } elseif (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s*(?:-|to|until|–|—)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b/i', $prompt, $m) === 1) {
            $startMinute = $m[2] === '' ? 0 : (int) $m[2];
            $endMinute = ! array_key_exists(5, $m) || $m[5] === '' ? 0 : (int) $m[5];
            $startMeridiem = $m[3] === '' ? null : $m[3];
            $endMeridiem = array_key_exists(6, $m) ? $m[6] : null;
            $startTime = $this->normalizeClock((int) $m[1], $startMinute, $startMeridiem);
            $endTime = $this->normalizeClock((int) $m[4], $endMinute, $endMeridiem);
        } elseif (str_contains($lower, 'morning')) {
            $startTime = '08:00:00';
            $endTime = '12:00:00';
        } elseif (str_contains($lower, 'evening')) {
            $startTime = '17:00:00';
            $endTime = '21:00:00';
        }

        return [
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'days_of_week' => $days,
        ];
    }

    private function normalizeClock(int $hour, int $minute, ?string $meridiem): string
    {
        if ($meridiem !== null) {
            $meridiem = strtolower($meridiem);
            if ($meridiem === 'pm' && $hour < 12) {
                $hour += 12;
            }
            if ($meridiem === 'am' && $hour === 12) {
                $hour = 0;
            }
        }

        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    private function hhmm(string $time): string
    {
        $normalized = ScheduleDefaults::normalizeTime($time);

        return $normalized ?? '09:00:00';
    }

    private function inferPurpose(string $prompt, string $fallback): string
    {
        $lower = mb_strtolower($prompt);
        $purposes = config('ai.design.purposes', []);

        foreach (['menu', 'event', 'welcome', 'announcement', 'information', 'promotion'] as $purpose) {
            if (str_contains($lower, $purpose) && in_array($purpose, $purposes, true)) {
                return $purpose;
            }
        }

        return in_array($fallback, $purposes, true) ? $fallback : 'promotion';
    }

    private function suggestedName(string $intent, string $prompt): string
    {
        $snippet = Str::limit(trim(preg_replace('/\s+/', ' ', $prompt) ?? $prompt), 40, '');

        return $snippet !== '' ? 'AI · '.$snippet : 'AI '.$intent;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @param  list<array{id: int, name: string, location: string|null}>  $screens
     */
    private function summaryFor(string $intent, array $steps, array $screens): string
    {
        $designs = count(array_filter($steps, static fn (array $s): bool => ($s['type'] ?? '') === 'create_design'));
        $playlists = count(array_filter($steps, static fn (array $s): bool => ($s['type'] ?? '') === 'create_playlist'));
        $schedules = count(array_filter($steps, static fn (array $s): bool => ($s['type'] ?? '') === 'create_schedule'));

        $parts = [];
        if ($designs > 0) {
            $parts[] = $designs.' design'.($designs === 1 ? '' : 's');
        }
        if ($playlists > 0) {
            $parts[] = $playlists.' playlist'.($playlists === 1 ? '' : 's');
        }
        if ($schedules > 0) {
            $parts[] = $schedules.' draft schedule'.($schedules === 1 ? '' : 's');
        }

        $base = $parts === []
            ? 'No actions proposed.'
            : 'Will create '.implode(', ', $parts).' (review before confirming).';

        if ($screens !== []) {
            $names = implode(', ', array_map(static fn (array $s): string => $s['name'], $screens));
            $base .= ' Screens: '.$names.'.';
        }

        $base .= ' Nothing goes live until you confirm';
        $base .= $intent === 'schedule' || $intent === 'compound'
            ? ' — schedules stay Draft unless you explicitly activate.'
            : '.';

        return $base;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function findStepIndex(array $steps, string $type): ?int
    {
        foreach ($steps as $i => $step) {
            if (($step['type'] ?? '') === $type) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function planHasSchedule(array $steps): bool
    {
        foreach ($steps as $step) {
            if (($step['type'] ?? '') === 'create_schedule') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<int>
     */
    private function screenIdsFromPlan(array $plan): array
    {
        $ids = [];
        foreach ($plan['resolved_screens'] ?? [] as $screen) {
            if (is_array($screen) && isset($screen['id'])) {
                $ids[] = (int) $screen['id'];
            }
        }

        foreach ($plan['steps'] ?? [] as $step) {
            if (! is_array($step) || ($step['type'] ?? '') !== 'create_schedule') {
                continue;
            }
            foreach ($step['screen_ids'] ?? [] as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }
}
