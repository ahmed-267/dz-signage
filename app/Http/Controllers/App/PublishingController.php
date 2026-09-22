<?php

namespace App\Http\Controllers\App;

use App\Actions\Deployments\PublishContentToScreens;
use App\Enums\DeploymentContentType;
use App\Enums\DeploymentStatus;
use App\Enums\PlaylistStatus;
use App\Enums\ScreenDesignStatus;
use App\Enums\ScreenOperationalStatus;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\Workspace;
use App\Support\ListPagination;
use App\Support\Schedules\ScheduleEvaluator;
use App\Support\Screens\ScreenContentResolver;
use App\Support\Screens\ScreenPresence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublishingController extends Controller
{
    public function __construct(
        private readonly ScreenContentResolver $resolver,
        private readonly ScheduleEvaluator $evaluator,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('viewAny', Deployment::class);

        $screens = Screen::query()
            ->forWorkspace($workspace)
            ->with([
                'devices' => fn ($q) => $q->whereNull('revoked_at')->orderByDesc('paired_at'),
                'deployments' => fn ($q) => $q
                    ->where('status', DeploymentStatus::Active)
                    ->with([
                        'screenDesign:id,name,orientation',
                        'screenDesignVersion:id,version_number',
                        'playlist:id,name,orientation',
                        'playlistVersion:id,version_number',
                        'deployer:id,name',
                    ])
                    ->latest('id'),
            ])
            ->orderBy('name')
            ->get();

        $live = [];
        $scheduled = [];

        foreach ($screens as $screen) {
            $device = $screen->devices->first();
            $activeDeployment = $screen->deployments->first();
            $resolved = $this->resolver->resolve($screen);
            $row = $this->liveRow($screen, $device, $activeDeployment, $resolved);

            if ($resolved->isSchedule()) {
                $scheduled[] = $row;
            }

            $live[] = $row;
        }

        $historyQuery = Deployment::query()
            ->where('workspace_id', $workspace->id)
            ->with([
                'screen:id,name,orientation,operational_status',
                'screenDesign:id,name,orientation',
                'screenDesignVersion:id,version_number',
                'playlist:id,name,orientation',
                'playlistVersion:id,version_number',
                'deployer:id,name',
            ]);

        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');
        $type = (string) $request->input('type', 'all');
        $screenId = (int) $request->input('screen', 0);
        $sort = ListPagination::sort(
            $request,
            ['status', 'type', 'deployed'],
            'deployed',
            'desc',
        );

        if ($q !== '') {
            $term = '%'.$q.'%';
            $historyQuery->where(function ($inner) use ($term): void {
                $inner->whereHas('screen', fn ($s) => $s->where('name', 'ilike', $term))
                    ->orWhereHas('screenDesign', fn ($d) => $d->where('name', 'ilike', $term))
                    ->orWhereHas('playlist', fn ($p) => $p->where('name', 'ilike', $term));
            });
        }

        if ($status !== 'all' && in_array($status, DeploymentStatus::values(), true)) {
            $historyQuery->where('status', $status);
        }

        if ($type === DeploymentContentType::ScreenDesign->value || $type === DeploymentContentType::Playlist->value) {
            $historyQuery->where('content_type', $type);
        }

        if ($screenId > 0) {
            $historyQuery->where('screen_id', $screenId);
        }

        $direction = $sort['direction'];
        $historyQuery = match ($sort['column']) {
            'status' => $historyQuery->orderBy('status', $direction)->orderByDesc('id'),
            'type' => $historyQuery->orderBy('content_type', $direction)->orderByDesc('id'),
            default => $historyQuery->orderBy('deployed_at', $direction)->orderByDesc('id'),
        };

        $history = $historyQuery
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('app/publishing/index', [
            'live' => $live,
            'scheduled' => $scheduled,
            'history' => [
                'data' => $history->getCollection()
                    ->map(fn (Deployment $d) => $this->historyRow($d))
                    ->values(),
                'meta' => [
                    'current_page' => $history->currentPage(),
                    'last_page' => $history->lastPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                ],
                'links' => [
                    'prev' => $history->previousPageUrl(),
                    'next' => $history->nextPageUrl(),
                ],
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'type' => $type,
                'screen' => $screenId > 0 ? $screenId : null,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'screens' => $this->screenOptions($workspace, $screens),
            'designs' => $this->publishedDesigns($workspace),
            'playlists' => $this->publishedPlaylists($workspace),
            'can_publish' => $user->can('create', Deployment::class),
            'statuses' => array_map(
                fn (DeploymentStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                DeploymentStatus::cases(),
            ),
        ]);
    }

    public function store(Request $request, PublishContentToScreens $action): RedirectResponse
    {
        $this->authorize('create', Deployment::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'content_type' => ['required', Rule::in([
                DeploymentContentType::ScreenDesign->value,
                DeploymentContentType::Playlist->value,
            ])],
            'content_id' => ['required', 'integer'],
            'screen_ids' => ['required', 'array', 'min:1'],
            'screen_ids.*' => ['integer'],
        ]);

        $result = $data['content_type'] === DeploymentContentType::Playlist->value
            ? $action->publishPlaylist(
                $request->user(),
                $workspace,
                Playlist::query()->whereKey((int) $data['content_id'])->firstOrFail(),
                $data['screen_ids'],
            )
            : $action->publishDesign(
                $request->user(),
                $workspace,
                ScreenDesign::query()->whereKey((int) $data['content_id'])->firstOrFail(),
                $data['screen_ids'],
            );

        $message = 'Published to '.$result['deployments']->count().' screen(s).';
        if ($result['warnings'] !== []) {
            $message .= ' '.implode(' ', $result['warnings']);
        }

        return redirect()
            ->route('app.publishing')
            ->with('success', $message)
            ->with('publish_warnings', $result['warnings']);
    }

    public function republish(
        Request $request,
        Deployment $deployment,
        PublishContentToScreens $action,
    ): RedirectResponse {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $workspace && (int) $deployment->workspace_id === (int) $workspace->id,
            404,
        );
        $this->authorize('republish', $deployment);

        $result = $action->republish($request->user(), $workspace, $deployment);

        $message = 'Republished to the screen.';
        if ($result['warnings'] !== []) {
            $message .= ' '.implode(' ', $result['warnings']);
        }

        return redirect()
            ->back()
            ->with('success', $message)
            ->with('publish_warnings', $result['warnings']);
    }

    /**
     * @param  mixed  $device
     * @return array<string, mixed>
     */
    private function liveRow(
        Screen $screen,
        $device,
        ?Deployment $activeDeployment,
        mixed $resolved,
    ): array {
        $sync = ScreenPresence::contentSyncState($activeDeployment, $device);
        $network = ScreenPresence::networkState($device);
        $pairing = ScreenPresence::pairingState($device);

        $ack = match (true) {
            $resolved->isSchedule() => 'scheduled',
            $activeDeployment === null => 'none',
            $sync->value === 'up_to_date' => 'live',
            $network === 'offline' && $pairing === 'connected' => 'waiting',
            $sync->value === 'out_of_sync' => 'updating',
            default => 'publishing',
        };

        $next = $this->evaluator->findNextForScreen($screen);

        return [
            'screen_id' => $screen->id,
            'screen_name' => $screen->name,
            'orientation' => $screen->orientation,
            'operational_status' => $screen->operational_status->value,
            'pairing_state' => $pairing,
            'network_state' => $network,
            'content_source' => $resolved->source->value,
            'content_name' => $resolved->isSchedule()
                ? ($resolved->playlist !== null ? $resolved->playlist->name : $resolved->schedule?->name)
                : ($activeDeployment?->contentName()),
            'content_type' => $resolved->isSchedule()
                ? 'schedule'
                : ($activeDeployment?->content_type->value),
            'version_number' => $resolved->isSchedule()
                ? $resolved->playlistVersion?->version_number
                : $activeDeployment?->contentVersionNumber(),
            'schedule_id' => $resolved->schedule?->id,
            'schedule_name' => $resolved->schedule?->name,
            'deployment_id' => $activeDeployment?->id,
            'deployed_at' => $activeDeployment?->deployed_at?->toIso8601String(),
            'sync_state' => $sync->value,
            'ack_state' => $ack,
            'ack_label' => match ($ack) {
                'live' => 'Live',
                'updating' => 'Updating',
                'waiting' => 'Waiting',
                'publishing' => 'Publishing',
                'scheduled' => 'Scheduled',
                default => 'No content',
            },
            'window_ends_at' => $resolved->windowEndsAt()?->toIso8601String(),
            'next_schedule' => $next === null ? null : [
                'id' => $next['schedule']->id,
                'name' => $next['schedule']->name,
                'starts_at' => $next['window']->startsAtUtc()->toIso8601String(),
                'starts_at_local' => $next['window']->startsAt
                    ->copy()
                    ->setTimezone($next['schedule']->timezone)
                    ->format('Y-m-d H:i'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyRow(Deployment $deployment): array
    {
        return [
            'id' => $deployment->id,
            'screen_id' => $deployment->screen_id,
            'screen_name' => $deployment->screen?->name,
            'content_type' => $deployment->content_type->value,
            'content_type_label' => $deployment->content_type->label(),
            'content_name' => $deployment->contentName(),
            'version_number' => $deployment->contentVersionNumber(),
            'status' => $deployment->status->value,
            'status_label' => $deployment->status->label(),
            'deployed_by_name' => $deployment->deployer?->name,
            'deployed_at' => $deployment->deployed_at?->toIso8601String(),
            'superseded_at' => $deployment->superseded_at?->toIso8601String(),
            'can_republish' => in_array($deployment->status, [
                DeploymentStatus::Active,
                DeploymentStatus::Superseded,
                DeploymentStatus::Pending,
            ], true),
        ];
    }

    /**
     * @param  Collection<int, Screen>  $screens
     * @return array<int, array<string, mixed>>
     */
    private function screenOptions(Workspace $workspace, $screens): array
    {
        return $screens->map(function (Screen $screen) {
            $device = $screen->devices->first();
            $active = $screen->deployments->first();

            return [
                'id' => $screen->id,
                'name' => $screen->name,
                'orientation' => $screen->orientation,
                'operational_status' => $screen->operational_status->value,
                'pairing_state' => ScreenPresence::pairingState($device),
                'network_state' => ScreenPresence::networkState($device),
                'current_content' => $active?->contentName(),
                'is_inactive' => $screen->operational_status === ScreenOperationalStatus::Inactive,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedDesigns(Workspace $workspace): array
    {
        return ScreenDesign::query()
            ->forWorkspace($workspace)
            ->where('status', ScreenDesignStatus::Published)
            ->whereNotNull('published_version_id')
            ->with('publishedVersion:id,version_number')
            ->orderBy('name')
            ->get(['id', 'name', 'orientation', 'published_version_id', 'status'])
            ->map(fn (ScreenDesign $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'orientation' => $d->orientation->value,
                'version_number' => $d->publishedVersion?->version_number,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedPlaylists(Workspace $workspace): array
    {
        return Playlist::query()
            ->forWorkspace($workspace)
            ->where('status', PlaylistStatus::Published)
            ->whereNotNull('published_version_id')
            ->with('publishedVersion:id,version_number')
            ->orderBy('name')
            ->get(['id', 'name', 'orientation', 'published_version_id', 'status'])
            ->map(fn (Playlist $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'orientation' => $p->orientation?->value,
                'version_number' => $p->publishedVersion?->version_number,
            ])
            ->values()
            ->all();
    }
}
