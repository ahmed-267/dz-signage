<?php

use App\Enums\DeploymentStatus;
use App\Enums\MediaType;
use App\Enums\ScreenDesignStatus;
use App\Enums\ScreenOperationalStatus;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceRole;
use App\Models\Deployment;
use App\Models\Location;
use App\Models\MediaAsset;
use App\Models\PairingSession;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\User;
use App\Support\Rendering\LayoutSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function screenUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function publishedDesignFor(User $user, $workspace, ?array $schema = null): ScreenDesign
{
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user, $schema)
        ->create(['name' => 'Lobby Design']);

    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();
    $design->forceFill([
        'status' => ScreenDesignStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $design->fresh(['publishedVersion']) ?? $design;
}

function pairScreenViaApi(User $user, string $name = 'Front Desk TV'): array
{
    $create = test()->postJson(route('player.api.pairing_sessions.store'))
        ->assertCreated()
        ->json();

    test()->actingAs($user)
        ->post(route('app.screens.pair.store'), [
            'code' => $create['code'],
            'name' => $name,
            'orientation' => 'landscape',
        ])
        ->assertRedirect();

    $poll = test()->getJson(route('player.api.pairing_sessions.show', $create['public_id']))
        ->assertOk()
        ->json();

    expect($poll['status'])->toBe('claimed')
        ->and($poll)->toHaveKey('device_token');

    $screen = Screen::query()->where('name', $name)->firstOrFail();

    return [
        'public_id' => $create['public_id'],
        'code' => $create['code'],
        'device_token' => $poll['device_token'],
        'screen' => $screen,
    ];
}

test('player can create a pairing session without auth', function () {
    $response = $this->postJson(route('player.api.pairing_sessions.store'))
        ->assertCreated()
        ->assertJsonStructure([
            'public_id',
            'code',
            'expires_at',
            'expires_in_seconds',
            'pair_url',
        ]);

    expect($response->json('code'))->toStartWith('DZ-')
        ->and(strlen($response->json('public_id')))->toBe(26);

    $session = PairingSession::query()->where('public_id', $response->json('public_id'))->first();
    expect($session)->not->toBeNull()
        ->and($session->verifyCode($response->json('code')))->toBeTrue();
});

test('owner can claim pairing code and player receives one-time device token', function () {
    [$user] = screenUser();

    $create = $this->postJson(route('player.api.pairing_sessions.store'))->assertCreated()->json();

    $this->getJson(route('player.api.pairing_sessions.show', $create['public_id']))
        ->assertOk()
        ->assertJson(['status' => 'pending']);

    $this->actingAs($user)
        ->post(route('app.screens.pair.store'), [
            'code' => $create['code'],
            'name' => 'Lobby TV',
            'orientation' => 'landscape',
        ])
        ->assertRedirect();

    $screen = Screen::query()->where('name', 'Lobby TV')->first();
    expect($screen)->not->toBeNull()
        ->and($screen->pairingState())->toBe('connected')
        ->and($screen->networkState())->toBe('offline')
        ->and($screen->activeDevice())->not->toBeNull();

    $firstPoll = $this->getJson(route('player.api.pairing_sessions.show', $create['public_id']))
        ->assertOk()
        ->json();

    expect($firstPoll['status'])->toBe('claimed')
        ->and($firstPoll['device_token'])->not->toBeEmpty();

    $secondPoll = $this->getJson(route('player.api.pairing_sessions.show', $create['public_id']))
        ->assertOk()
        ->json();

    expect($secondPoll['status'])->toBe('claimed')
        ->and($secondPoll)->not->toHaveKey('device_token');

    $session = PairingSession::query()->where('public_id', $create['public_id'])->first();
    expect($session->pending_device_token_ciphertext)->toBeNull();
});

test('claim by public id works and expired sessions cannot be reused', function () {
    [$user] = screenUser();

    $create = $this->postJson(route('player.api.pairing_sessions.store'))->assertCreated()->json();

    $this->actingAs($user)
        ->post(route('app.screens.pair.claim', $create['public_id']), [
            'name' => 'QR Paired',
        ])
        ->assertRedirect();

    expect(Screen::query()->where('name', 'QR Paired')->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('app.screens.pair.store'), [
            'code' => $create['code'],
            'name' => 'Reuse Attempt',
        ])
        ->assertSessionHasErrors('code');

    $expired = PairingSession::factory()->expired()->withCode('DZ-ZZZZ')->create();

    $this->actingAs($user)
        ->post(route('app.screens.pair.store'), [
            'code' => 'DZ-ZZZZ',
            'name' => 'Too Late',
        ])
        ->assertSessionHasErrors('code');

    $this->getJson(route('player.api.pairing_sessions.show', $expired->public_id))
        ->assertOk()
        ->assertJson(['status' => 'expired']);
});

test('workspace isolation blocks foreign screens and claims stay in current workspace', function () {
    [$userA, $workspaceA] = screenUser();
    [$userB] = screenUser();

    $paired = pairScreenViaApi($userA, 'Private Screen');

    $this->actingAs($userB)
        ->get(route('app.screens.show', $paired['screen']))
        ->assertNotFound();

    $this->actingAs($userB)
        ->post(route('app.screens.rename', $paired['screen']), ['name' => 'Hacked'])
        ->assertNotFound();

    expect($paired['screen']->fresh()->workspace_id)->toBe($workspaceA->id)
        ->and($paired['screen']->fresh()->name)->toBe('Private Screen');
});

test('location manager can pair while content manager cannot; content manager can publish', function () {
    [$owner, $workspace] = screenUser();
    $locationManager = User::factory()->create();
    $contentManager = User::factory()->create();
    attachWorkspace($locationManager, $workspace, WorkspaceRole::LocationManager);
    attachWorkspace($contentManager, $workspace, WorkspaceRole::ContentManager);

    $location = Location::factory()
        ->forWorkspace($workspace)
        ->createdBy($owner)
        ->create();
    $location->managers()->attach($locationManager->id);

    $create = $this->postJson(route('player.api.pairing_sessions.store'))->assertCreated()->json();

    $this->actingAs($contentManager)
        ->post(route('app.screens.pair.store'), [
            'code' => $create['code'],
            'name' => 'Blocked',
        ])
        ->assertForbidden();

    $this->actingAs($locationManager)
        ->post(route('app.screens.pair.store'), [
            'code' => $create['code'],
            'name' => 'LM Screen',
            'location_id' => $location->id,
        ])
        ->assertRedirect();

    $screen = Screen::query()->where('name', 'LM Screen')->firstOrFail();
    expect($screen->location_id)->toBe($location->id);

    $design = publishedDesignFor($owner, $workspace);

    $this->actingAs($locationManager)
        ->post(route('app.screens.publish', $screen), [
            'screen_design_id' => $design->id,
        ])
        ->assertForbidden();

    $this->actingAs($contentManager)
        ->post(route('app.screens.publish', $screen), [
            'screen_design_id' => $design->id,
        ])
        ->assertRedirect();

    expect($screen->fresh()->activeDeployment())->not->toBeNull();
});

test('device auth rejects missing revoked tokens and updates last_seen', function () {
    [$user] = screenUser();
    $paired = pairScreenViaApi($user);

    $this->getJson(route('player.api.manifest'))
        ->assertUnauthorized();

    $this->withToken($paired['device_token'])
        ->getJson(route('player.api.manifest.check'))
        ->assertOk()
        ->assertJson([
            'screen_active' => true,
            'deployment_id' => null,
        ]);

    $device = ScreenDevice::findByToken($paired['device_token']);
    expect($device->last_seen_at)->not->toBeNull();
    expect($device->screen->networkState())->toBe('online');

    $this->actingAs($user)
        ->delete(route('app.screens.destroy', $paired['screen']))
        ->assertRedirect(route('app.screens'));

    expect($paired['screen']->fresh()->pairingState())->toBe('disconnected');

    $this->withToken($paired['device_token'])
        ->getJson(route('player.api.manifest'))
        ->assertUnauthorized();
});

test('only published designs can be deployed and draft is rejected', function () {
    [$user, $workspace] = screenUser();
    $paired = pairScreenViaApi($user);
    $draft = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => 'Draft Only']);

    $this->actingAs($user)
        ->post(route('app.screen_designs.publish_to_screens', $draft), [
            'screen_ids' => [$paired['screen']->id],
        ])
        ->assertSessionHasErrors('design');

    $published = publishedDesignFor($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.screen_designs.publish_to_screens', $published), [
            'screen_ids' => [$paired['screen']->id],
        ])
        ->assertRedirect();

    $deployment = $paired['screen']->fresh()->activeDeployment();
    expect($deployment)->not->toBeNull()
        ->and($deployment->screen_design_id)->toBe($published->id)
        ->and($deployment->status)->toBe(DeploymentStatus::Active);
});

test('publishing to multiple screens supersedes previous active deployments', function () {
    [$user, $workspace] = screenUser();
    $first = pairScreenViaApi($user, 'Screen One');
    $second = pairScreenViaApi($user, 'Screen Two');

    $designA = publishedDesignFor($user, $workspace);
    $designB = publishedDesignFor($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.screen_designs.publish_to_screens', $designA), [
            'screen_ids' => [$first['screen']->id, $second['screen']->id],
        ])
        ->assertRedirect();

    $oldFirst = $first['screen']->fresh()->activeDeployment();
    expect($oldFirst)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('app.screens.publish', $first['screen']), [
            'screen_design_id' => $designB->id,
        ])
        ->assertRedirect();

    expect($oldFirst->fresh()->status)->toBe(DeploymentStatus::Superseded)
        ->and($first['screen']->fresh()->activeDeployment()->screen_design_id)->toBe($designB->id)
        ->and($second['screen']->fresh()->activeDeployment()->screen_design_id)->toBe($designA->id);
});

test('manifest returns ready inactive and no_content statuses', function () {
    [$user, $workspace] = screenUser();
    $paired = pairScreenViaApi($user);

    $this->withHeader('X-Device-Token', $paired['device_token'])
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->assertJson(['status' => 'no_content', 'screenId' => $paired['screen']->id]);

    $design = publishedDesignFor($user, $workspace);
    $this->actingAs($user)
        ->post(route('app.screens.publish', $paired['screen']), [
            'screen_design_id' => $design->id,
        ])
        ->assertRedirect();

    $manifest = $this->withToken($paired['device_token'])
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->json();

    expect($manifest['status'])->toBe('ready')
        ->and($manifest['deploymentVersion'])->toStartWith('dep-')
        ->and($manifest['screenDesignId'])->toBe($design->id)
        ->and($manifest['schema'])->toBeArray();

    $this->actingAs($user)
        ->post(route('app.screens.status', $paired['screen']), [
            'operational_status' => ScreenOperationalStatus::Inactive->value,
        ])
        ->assertRedirect();

    $this->withToken($paired['device_token'])
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->assertJson(['status' => 'inactive']);
});

test('player media is limited to active deployment schema and workspace', function () {
    Storage::fake('public');
    [$user, $workspace] = screenUser();
    [$otherUser, $otherWorkspace] = screenUser();
    $paired = pairScreenViaApi($user);

    $file = UploadedFile::fake()->image('lobby.jpg');
    $path = $file->store('workspaces/'.$workspace->id.'/media', 'public');

    $media = MediaAsset::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => MediaType::Image,
        'name' => 'Lobby Art',
        'storage_disk' => 'public',
        'storage_path' => $path,
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'created_by' => $user->id,
    ]);

    $foreign = MediaAsset::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'type' => MediaType::Image,
        'name' => 'Foreign',
        'storage_disk' => 'public',
        'storage_path' => $file->store('workspaces/'.$otherWorkspace->id.'/media', 'public'),
        'mime_type' => 'image/jpeg',
        'created_by' => $otherUser->id,
    ]);

    $schema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Blank);
    $schema['elements'] = [[
        'id' => 'image-1',
        'type' => 'image',
        'name' => 'Hero',
        'x' => 0,
        'y' => 0,
        'width' => 400,
        'height' => 300,
        'zIndex' => 1,
        'props' => ['mediaAssetId' => $media->id],
    ]];

    $design = publishedDesignFor($user, $workspace, $schema);

    $this->actingAs($user)
        ->post(route('app.screens.publish', $paired['screen']), [
            'screen_design_id' => $design->id,
        ])
        ->assertRedirect();

    $this->withToken($paired['device_token'])
        ->get(route('player.api.media.show', $media))
        ->assertOk();

    $this->withToken($paired['device_token'])
        ->get(route('player.api.media.show', $foreign))
        ->assertForbidden();

    $unused = MediaAsset::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => MediaType::Image,
        'name' => 'Unused',
        'storage_disk' => 'public',
        'storage_path' => $file->store('workspaces/'.$workspace->id.'/media', 'public'),
        'mime_type' => 'image/jpeg',
        'created_by' => $user->id,
    ]);

    $this->withToken($paired['device_token'])
        ->get(route('player.api.media.show', $unused))
        ->assertForbidden();
});

test('deleting a design is blocked while actively deployed', function () {
    [$user, $workspace] = screenUser();
    $paired = pairScreenViaApi($user);
    $design = publishedDesignFor($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.screens.publish', $paired['screen']), [
            'screen_design_id' => $design->id,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->delete(route('app.screen_designs.destroy', $design))
        ->assertSessionHasErrors('design');

    expect(ScreenDesign::query()->whereKey($design->id)->exists())->toBeTrue();

    Deployment::query()
        ->where('screen_design_id', $design->id)
        ->update([
            'status' => DeploymentStatus::Superseded->value,
            'superseded_at' => now(),
        ]);

    $this->actingAs($user)
        ->delete(route('app.screen_designs.destroy', $design))
        ->assertRedirect(route('app.screen_designs'));

    expect(ScreenDesign::query()->whereKey($design->id)->exists())->toBeFalse();
});

test('rename screen and admin screens index work', function () {
    [$user] = screenUser();
    $paired = pairScreenViaApi($user);
    $admin = User::factory()->admin()->create();

    $this->actingAs($user)
        ->post(route('app.screens.rename', $paired['screen']), [
            'name' => 'Renamed Lobby',
        ])
        ->assertRedirect();

    expect($paired['screen']->fresh()->name)->toBe('Renamed Lobby');

    $this->actingAs($admin)
        ->get(route('admin.screens'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/screens/index')
            ->has('screens'));
});

test('pending device token ciphertext is encrypted at rest', function () {
    [$user] = screenUser();
    $create = $this->postJson(route('player.api.pairing_sessions.store'))->assertCreated()->json();

    $this->actingAs($user)
        ->post(route('app.screens.pair.store'), [
            'code' => $create['code'],
            'name' => 'Encrypted Token Screen',
        ])
        ->assertRedirect();

    $session = PairingSession::query()->where('public_id', $create['public_id'])->firstOrFail();
    expect($session->pending_device_token_ciphertext)->not->toBeNull();

    $plain = Crypt::decryptString($session->pending_device_token_ciphertext);
    expect($plain)->not->toBeEmpty()
        ->and(Str::length($plain))->toBeGreaterThan(20)
        ->and($session->pending_device_token_ciphertext)->not->toContain($plain);
});
