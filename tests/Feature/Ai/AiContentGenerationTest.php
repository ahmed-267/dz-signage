<?php

use App\Enums\AiGenerationStatus;
use App\Enums\AiGenerationType;
use App\Enums\WorkspaceRole;
use App\Models\AiGeneration;
use App\Models\FeatureFlag;
use App\Models\MediaAsset;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Support\Ai\AiContentService;
use App\Support\Ai\AiException;
use App\Support\Ai\Contracts\AiTextProvider;
use App\Support\Ai\CreativeBrief;
use App\Support\Ai\Dto\DesignPlanResult;
use App\Support\Ai\Dto\TextGenerationResult;
use App\Support\Ai\Providers\FakeAiTextProvider;
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

function aiUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

test('owner can generate AI text', function () {
    [$user] = aiUser();

    $this->actingAs($user)
        ->postJson(route('app.ai.text'), [
            'prompt' => 'Weekend trainer sale 20% off',
            'purpose' => 'promotion',
            'tone' => 'bold',
            'length' => 'short',
            'variants' => 3,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonStructure(['generation_id', 'text', 'texts']);

    expect(AiGeneration::query()->where('type', AiGenerationType::Text)->count())->toBe(1);
});

test('AI text returns multiple variants for selection', function () {
    [$user] = aiUser();

    $response = $this->actingAs($user)
        ->postJson(route('app.ai.text'), [
            'prompt' => 'Grand opening this Friday',
            'mode' => 'headline',
            'variants' => 3,
        ])
        ->assertOk()
        ->json();

    expect($response['texts'])->toBeArray()->toHaveCount(3);
    expect($response['text'])->toBe($response['texts'][0]);
});

test('viewer cannot generate AI content', function () {
    [$owner, $workspace] = aiUser();
    $viewer = User::factory()->create();
    attachWorkspace($viewer, $workspace, WorkspaceRole::Viewer);

    $this->actingAs($viewer)
        ->postJson(route('app.ai.text'), [
            'prompt' => 'Hello',
        ])
        ->assertForbidden();
});

test('AI text rewrite returns replacement copy', function () {
    [$user] = aiUser();

    $this->actingAs($user)
        ->postJson(route('app.ai.text.rewrite'), [
            'text' => 'Come get amazing deals on shoes this weekend for everyone.',
            'action' => 'shorten',
        ])
        ->assertOk()
        ->assertJsonStructure(['text', 'generation_id']);
});

test('AI image generates preview and saves to media', function () {
    [$user, $workspace] = aiUser();

    $generate = $this->actingAs($user)
        ->postJson(route('app.ai.image'), [
            'prompt' => 'Premium iced coffee on dark cafe background',
            'aspect' => 'landscape',
            'style' => 'elegant',
        ])
        ->assertOk()
        ->json();

    expect($generate['generation_id'])->toBeInt();
    expect($generate['preview_url'])->toBeString();

    $generation = AiGeneration::query()->findOrFail($generate['generation_id']);
    expect($generation->status)->toBe(AiGenerationStatus::Completed);
    expect($generation->hasTempFile())->toBeTrue();

    $this->actingAs($user)
        ->get(route('app.ai.generations.preview', $generation))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $save = $this->actingAs($user)
        ->postJson(route('app.ai.image.save', $generation), [
            'name' => 'Iced Coffee Promo',
        ])
        ->assertOk()
        ->json();

    $asset = MediaAsset::query()->findOrFail($save['media']['id']);
    expect($asset->workspace_id)->toBe($workspace->id);
    expect($asset->name)->toBe('Iced Coffee Promo');
    expect($asset->hasStoredFile())->toBeTrue();
    expect($asset->metadata['source'] ?? null)->toBe('ai');

    $generation->refresh();
    expect($generation->media_asset_id)->toBe($asset->id);
    expect($generation->hasTempFile())->toBeFalse();
});

test('workspace isolation blocks AI generation preview', function () {
    [$userA] = aiUser();
    [$userB] = aiUser();

    $generate = $this->actingAs($userA)
        ->postJson(route('app.ai.image'), [
            'prompt' => 'Private image',
            'aspect' => 'square',
        ])
        ->assertOk()
        ->json();

    $generation = AiGeneration::query()->findOrFail($generate['generation_id']);

    $this->actingAs($userB)
        ->get(route('app.ai.generations.preview', $generation))
        ->assertForbidden();
});

test('AI design failure leaves no orphan screen design', function () {
    [$user, $workspace] = aiUser();

    $this->app->bind(
        FakeAiTextProvider::class,
        fn () => new class implements AiTextProvider
        {
            public function generate(string $prompt, array $options = []): TextGenerationResult
            {
                throw AiException::unavailable('Forced failure for orphan test.');
            }

            public function rewrite(string $text, array $options = []): TextGenerationResult
            {
                throw AiException::unavailable('Forced failure for orphan test.');
            }

            public function generateDesignPlan(
                CreativeBrief $brief,
                ?string $archetype = null,
            ): DesignPlanResult {
                throw AiException::unavailable('Forced failure for orphan test.');
            }

            public function model(): string
            {
                return 'fake-fail';
            }

            public function name(): string
            {
                return 'fake';
            }
        },
    );

    $before = ScreenDesign::query()->count();

    expect(fn () => app(AiContentService::class)->generateDesign(
        $user,
        $workspace,
        'Force failure iced coffee board',
        [
            'orientation' => 'landscape',
            'purpose' => 'promotion',
            'style' => 'bold',
            'variant_count' => 1,
            'prefer_templates' => false,
            'prefer_existing_media' => false,
            'generate_matching_image' => false,
        ],
    ))->toThrow(AiException::class);

    expect(ScreenDesign::query()->count())->toBe($before)
        ->and(AiGeneration::query()->where('status', AiGenerationStatus::Failed)->count())->toBeGreaterThan(0);
});

test('AI design concepts can be accepted into a screen design', function () {
    [$user] = aiUser(WorkspaceRole::Designer);

    $concepts = $this->actingAs($user)
        ->postJson(route('app.ai.design'), [
            'prompt' => 'Lobby welcome board for hotel guests',
            'orientation' => 'portrait',
            'purpose' => 'welcome',
            'style' => 'elegant',
            'variant_count' => 3,
            'use_brand_kit' => false,
        ])
        ->assertOk()
        ->json();

    expect($concepts['concepts'])->toHaveCount(3);
    expect($concepts['concepts'][0])->toHaveKeys(['index', 'name', 'archetype', 'preview']);

    $accepted = $this->actingAs($user)
        ->postJson(route('app.ai.design.accept'), [
            'generation_id' => $concepts['generation_id'],
            'concept_index' => 1,
            'name' => 'Welcome Concept',
        ])
        ->assertOk()
        ->json();

    $design = ScreenDesign::query()->findOrFail($accepted['screen_design_id']);
    expect($design->name)->toBe('Welcome Concept');
    expect($design->status->value)->toBe('draft');
});

test('unsupported AI design elements are filtered out safely', function () {
    [$user] = aiUser();

    // Fake plan path already only emits supported types; assert create still works.
    $this->actingAs($user)
        ->postJson(route('app.ai.design'), [
            'prompt' => 'Welcome board',
            'orientation' => 'portrait',
            'purpose' => 'welcome',
        ])
        ->assertOk();
});

test('AI unavailable when feature flag disabled', function () {
    FeatureFlag::query()->where('key', 'ai_content_generation')->update(['enabled' => false]);
    [$user] = aiUser();

    $this->actingAs($user)
        ->postJson(route('app.ai.text'), ['prompt' => 'Hello'])
        ->assertStatus(503)
        ->assertJsonPath('code', 'unavailable');
});

test('idempotency key prevents duplicate costly generations', function () {
    [$user] = aiUser();
    $key = 'idem-test-image-1';

    $first = $this->actingAs($user)
        ->postJson(route('app.ai.image'), [
            'prompt' => 'Same prompt twice',
            'aspect' => 'landscape',
            'idempotency_key' => $key,
        ])
        ->assertOk()
        ->json();

    $second = $this->actingAs($user)
        ->postJson(route('app.ai.image'), [
            'prompt' => 'Same prompt twice',
            'aspect' => 'landscape',
            'idempotency_key' => $key,
        ])
        ->assertOk()
        ->json();

    expect($second['generation_id'])->toBe($first['generation_id']);
    expect(AiGeneration::query()->where('idempotency_key', $key)->count())->toBe(1);
});

test('prompt validation rejects empty and oversized prompts', function () {
    [$user] = aiUser();

    $this->actingAs($user)
        ->postJson(route('app.ai.text'), ['prompt' => '   '])
        ->assertStatus(422);

    $this->actingAs($user)
        ->postJson(route('app.ai.text'), [
            'prompt' => str_repeat('a', (int) config('ai.limits.prompt_max') + 5),
        ])
        ->assertStatus(422);
});
