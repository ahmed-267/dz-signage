<?php

use App\Enums\AiGenerationType;
use App\Enums\MediaType;
use App\Enums\PlaylistStatus;
use App\Enums\ScheduleStatus;
use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateStatus;
use App\Enums\WorkspaceRole;
use App\Models\AiGeneration;
use App\Models\FeatureFlag;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\Template;
use App\Models\User;
use App\Support\Ai\MediaRelevance;
use App\Support\Ai\TemplateRelevance;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('ai.temp.disk', 'public'));
    Storage::fake(config('media.disk', 'public'));
    config([
        'ai.enabled' => true,
        'ai.provider' => 'fake',
    ]);
    FeatureFlag::query()->updateOrCreate(
        ['key' => 'ai_content_generation'],
        [
            'name' => 'AI Content Generation',
            'description' => 'Test flag',
            'enabled' => true,
        ],
    );
    RateLimiter::clear('ai-user:1');
});

function agentUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function agentPublishedTemplate(User $creator, array $attributes = []): Template
{
    $template = Template::factory()
        ->platform()
        ->createdBy($creator)
        ->withDraftVersion($creator)
        ->create(array_merge([
            'name' => 'Promo Retail Banner',
            'category' => TemplateCategory::Promo,
            'orientation' => TemplateOrientation::Landscape,
            'status' => TemplateStatus::Draft,
            'industry' => 'retail',
        ], $attributes));

    $version = $template->versions()->first();
    $version->forceFill(['published_at' => now()])->save();
    $template->forceFill([
        'status' => TemplateStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $template->fresh(['publishedVersion']) ?? $template;
}

test('media relevance only scores assets from the current workspace', function () {
    [$userA, $workspaceA] = agentUser();
    [$userB, $workspaceB] = agentUser();

    $local = MediaAsset::factory()->forWorkspace($workspaceA)->createdBy($userA)->create([
        'type' => MediaType::Image,
        'name' => 'Iced coffee promo hero',
        'metadata' => ['ai_prompt' => 'iced coffee on dark cafe background'],
    ]);

    MediaAsset::factory()->forWorkspace($workspaceB)->createdBy($userB)->create([
        'type' => MediaType::Image,
        'name' => 'Iced coffee promo hero',
        'metadata' => ['ai_prompt' => 'iced coffee on dark cafe background'],
    ]);

    $matches = MediaRelevance::topMatches($workspaceA, 'iced coffee promo for cafe screens');

    expect($matches)->not->toBeEmpty()
        ->and($matches[0]['asset']->id)->toBe($local->id)
        ->and(collect($matches)->pluck('asset.workspace_id')->unique()->all())->toBe([$workspaceA->id]);
});

test('template relevance prefers matching purpose industry and orientation', function () {
    $staff = User::factory()->create();
    attachWorkspace($staff);

    $promo = agentPublishedTemplate($staff, [
        'name' => 'Spring Promo',
        'category' => TemplateCategory::Promo,
        'industry' => 'retail',
        'orientation' => TemplateOrientation::Landscape,
    ]);

    agentPublishedTemplate($staff, [
        'name' => 'Quiet Menu',
        'category' => TemplateCategory::Menu,
        'industry' => 'restaurant',
        'orientation' => TemplateOrientation::Portrait,
    ]);

    $matches = TemplateRelevance::topMatches('spring retail promo sale', [
        'purpose' => 'promotion',
        'industry' => 'retail',
        'orientation' => 'landscape',
        'limit' => 3,
    ]);

    expect($matches)->not->toBeEmpty()
        ->and($matches[0]['template']->id)->toBe($promo->id)
        ->and(TemplateRelevance::isHighConfidence($matches[0]['score']))->toBeTrue();
});

test('agent proposes and confirms a playlist draft from designs without deploying', function () {
    [$user] = agentUser();

    $propose = $this->actingAs($user)
        ->postJson(route('app.ai.agent.propose'), [
            'prompt' => 'Create a playlist loop for weekend shoe sale promo',
            'preferred_intent' => 'playlist',
        ])
        ->assertOk()
        ->json();

    expect($propose['intent'])->toBeIn(['playlist', 'compound'])
        ->and($propose['proposal_id'])->toBeInt()
        ->and($propose['will_activate'])->toBeFalse()
        ->and($propose['will_deploy'])->toBeFalse();

    $types = collect($propose['steps'])->pluck('type')->all();
    expect($types)->toContain('create_design')
        ->and($types)->toContain('create_playlist');

    $confirm = $this->actingAs($user)
        ->postJson(route('app.ai.agent.confirm'), [
            'proposal_id' => $propose['proposal_id'],
            'confirm' => true,
            'activate' => false,
            'deploy' => false,
        ])
        ->assertOk()
        ->json();

    expect($confirm['playlists'])->not->toBeEmpty()
        ->and($confirm['designs'])->not->toBeEmpty()
        ->and($confirm['activated'])->toBeFalse()
        ->and($confirm['deployed'])->toBeFalse();

    $playlist = Playlist::query()->findOrFail($confirm['playlists'][0]['id']);
    expect($playlist->status)->toBe(PlaylistStatus::Draft)
        ->and($playlist->versions()->first()?->items()->count())->toBeGreaterThan(0);

    $design = ScreenDesign::query()->findOrFail($confirm['designs'][0]['id']);
    expect($design->published_version_id)->not->toBeNull();
});

test('agent schedule proposal creates draft and never auto-activates', function () {
    [$user, $workspace] = agentUser();

    Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create(['name' => 'Lobby TV', 'orientation' => 'landscape']);

    $propose = $this->actingAs($user)
        ->postJson(route('app.ai.agent.propose'), [
            'prompt' => 'Schedule a welcome playlist weekdays 9am-5pm on Lobby TV',
            'preferred_intent' => 'schedule',
        ])
        ->assertOk()
        ->json();

    expect($propose['intent'])->toBeIn(['schedule', 'compound'])
        ->and($propose['resolved_screens'])->not->toBeEmpty()
        ->and($propose['resolved_screens'][0]['name'])->toBe('Lobby TV')
        ->and($propose['will_activate'])->toBeFalse();

    $confirm = $this->actingAs($user)
        ->postJson(route('app.ai.agent.confirm'), [
            'proposal_id' => $propose['proposal_id'],
            'confirm' => true,
        ])
        ->assertOk()
        ->json();

    expect($confirm['schedules'])->not->toBeEmpty()
        ->and($confirm['activated'])->toBeFalse();

    $schedule = Schedule::query()->findOrFail($confirm['schedules'][0]['id']);
    expect($schedule->status)->toBe(ScheduleStatus::Draft)
        ->and($schedule->activated_at)->toBeNull()
        ->and($schedule->screens()->pluck('screens.id')->all())->not->toBeEmpty();
});

test('compound propose and confirm creates design playlist and draft schedule', function () {
    [$user, $workspace] = agentUser();

    Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create(['name' => 'Entrance Screen', 'orientation' => 'landscape']);

    $propose = $this->actingAs($user)
        ->postJson(route('app.ai.agent.propose'), [
            'prompt' => 'Design a spring promo, make a playlist, and schedule it weekdays on Entrance Screen',
        ])
        ->assertOk()
        ->json();

    expect($propose['intent'])->toBe('compound');

    $types = collect($propose['steps'])->pluck('type')->all();
    expect($types)->toContain('create_design')
        ->and($types)->toContain('create_playlist')
        ->and($types)->toContain('create_schedule');

    $confirm = $this->actingAs($user)
        ->postJson(route('app.ai.agent.confirm'), [
            'proposal_id' => $propose['proposal_id'],
            'confirm' => true,
            'activate' => false,
            'deploy' => false,
        ])
        ->assertOk()
        ->json();

    expect($confirm['designs'])->not->toBeEmpty()
        ->and($confirm['playlists'])->not->toBeEmpty()
        ->and($confirm['schedules'])->not->toBeEmpty()
        ->and($confirm['activated'])->toBeFalse()
        ->and($confirm['deployed'])->toBeFalse();

    $schedule = Schedule::query()->findOrFail($confirm['schedules'][0]['id']);
    expect($schedule->status)->toBe(ScheduleStatus::Draft)
        ->and($schedule->playlist_id)->not->toBeNull();

    $playlist = Playlist::query()->findOrFail($confirm['playlists'][0]['id']);
    // Playlist is finalized so the schedule can pin a version — still not deployed.
    expect($playlist->status)->toBe(PlaylistStatus::Published);
});

test('cross-workspace denial blocks confirming another workspace proposal', function () {
    [$userA] = agentUser();
    [$userB] = agentUser();

    $propose = $this->actingAs($userA)
        ->postJson(route('app.ai.agent.propose'), [
            'prompt' => 'Create a design for private workspace A',
            'preferred_intent' => 'design',
        ])
        ->assertOk()
        ->json();

    $this->actingAs($userB)
        ->postJson(route('app.ai.agent.confirm'), [
            'proposal_id' => $propose['proposal_id'],
            'confirm' => true,
        ])
        ->assertForbidden();

    expect(AiGeneration::query()->whereKey($propose['proposal_id'])->value('type'))
        ->toBe(AiGenerationType::Agent);
});

test('confirm without confirm flag is rejected and creates nothing', function () {
    [$user] = agentUser();

    $propose = $this->actingAs($user)
        ->postJson(route('app.ai.agent.propose'), [
            'prompt' => 'Weekend playlist of promo designs',
            'preferred_intent' => 'playlist',
        ])
        ->assertOk()
        ->json();

    $this->actingAs($user)
        ->postJson(route('app.ai.agent.confirm'), [
            'proposal_id' => $propose['proposal_id'],
            'confirm' => false,
        ])
        ->assertUnprocessable();

    expect(Playlist::query()->count())->toBe(0)
        ->and(ScreenDesign::query()->count())->toBe(0);
});

test('design generation binds relevant workspace media before inventing images', function () {
    [$user, $workspace] = agentUser();

    $asset = MediaAsset::factory()->forWorkspace($workspace)->createdBy($user)->create([
        'type' => MediaType::Image,
        'name' => 'Trainer sale hero',
        'metadata' => ['ai_prompt' => 'trainer sale sneakers'],
    ]);

    $result = $this->actingAs($user)
        ->postJson(route('app.ai.design'), [
            'prompt' => 'Trainer sale sneakers promo board',
            'orientation' => 'landscape',
            'purpose' => 'promotion',
            'style' => 'bold',
            'variant_count' => 1,
            'generate_matching_image' => false,
        ])
        ->assertOk()
        ->json();

    $design = ScreenDesign::query()->findOrFail($result['screen_design_id']);
    $schema = $design->latestVersion()?->schema ?? [];
    $bound = collect($schema['elements'] ?? [])
        ->filter(fn ($el) => ($el['type'] ?? '') === 'image')
        ->pluck('props.mediaAssetId')
        ->filter()
        ->values();

    expect($bound->contains($asset->id))->toBeTrue();

    $generation = AiGeneration::query()->findOrFail($result['generation_id']);
    expect($generation->output['bound_media_ids'] ?? [])->toContain($asset->id)
        ->and($generation->output['matching_image_id'] ?? null)->toBeNull();
});

test('agent honours requested design count duration and exact tv names', function () {
    [$user, $workspace] = agentUser();

    Screen::factory()->forWorkspace($workspace)->createdBy($user)->create(['name' => 'Bar TV']);
    Screen::factory()->forWorkspace($workspace)->createdBy($user)->create(['name' => 'Reception TV']);
    Screen::factory()->forWorkspace($workspace)->createdBy($user)->create(['name' => 'Window TV']);

    $propose = $this->actingAs($user)
        ->postJson(route('app.ai.agent.propose'), [
            'prompt' => 'Create a football playlist with exactly 3 Screen Designs. Each should play for 15 seconds once. Show it on Bar TV Saturday from 6pm until 11pm.',
        ])
        ->assertOk()
        ->json();

    $designSteps = collect($propose['steps'])->where('type', 'create_design');
    $playlist = collect($propose['steps'])->firstWhere('type', 'create_playlist');

    expect($designSteps)->toHaveCount(3)
        ->and($playlist['duration_seconds'] ?? null)->toBe(15)
        ->and($playlist['loop_count'] ?? null)->toBe(1)
        ->and(collect($propose['resolved_screens'])->pluck('name')->all())->toBe(['Bar TV']);
});

test('ambiguous lobby tv names are not guessed', function () {
    [$user, $workspace] = agentUser();

    Screen::factory()->forWorkspace($workspace)->createdBy($user)->create(['name' => 'Main Lobby TV']);
    Screen::factory()->forWorkspace($workspace)->createdBy($user)->create(['name' => 'Upstairs Lobby TV']);

    $propose = $this->actingAs($user)
        ->postJson(route('app.ai.agent.propose'), [
            'prompt' => 'Put the welcome playlist on Lobby TV Saturday from 6pm until 11pm',
            'preferred_intent' => 'schedule',
        ])
        ->assertOk()
        ->json();

    expect($propose['resolved_screens'])->toBe([])
        ->and($propose['ambiguous_screens'])->toHaveCount(2);

    $this->actingAs($user)
        ->postJson(route('app.ai.agent.confirm'), [
            'proposal_id' => $propose['proposal_id'],
            'confirm' => true,
        ])
        ->assertUnprocessable();
});
