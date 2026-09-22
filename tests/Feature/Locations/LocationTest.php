<?php

use App\Enums\WorkspaceRole;
use App\Models\Location;
use App\Models\Screen;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function locationUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

test('owner can create and view a location', function () {
    [$user, $workspace] = locationUser();

    $this->actingAs($user)
        ->post(route('app.locations.store'), [
            'name' => 'Oxford Street',
            'city' => 'London',
            'country' => 'UK',
            'timezone' => 'Europe/London',
        ])
        ->assertRedirect();

    $location = Location::query()->where('name', 'Oxford Street')->first();
    expect($location)->not->toBeNull()
        ->and($location->workspace_id)->toBe($workspace->id)
        ->and($location->country)->toBe('UK');

    $this->actingAs($user)
        ->get(route('app.locations.show', $location))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/locations/show')
            ->where('location.name', 'Oxford Street')
            ->where('location.city', 'London')
        );
});

test('locations are workspace isolated', function () {
    [$ownerA, $workspaceA] = locationUser();
    [$ownerB] = locationUser();

    $location = Location::factory()
        ->forWorkspace($workspaceA)
        ->createdBy($ownerA)
        ->create(['name' => 'Private Site']);

    $this->actingAs($ownerB)
        ->get(route('app.locations.show', $location))
        ->assertNotFound();

    $this->actingAs($ownerB)
        ->patch(route('app.locations.update', $location), [
            'name' => 'Hijacked',
            'timezone' => 'Europe/London',
            'country' => 'UK',
        ])
        ->assertNotFound();
});

test('owner can assign a screen to a location', function () {
    [$user, $workspace] = locationUser();

    $location = Location::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create();

    $screen = Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create(['name' => 'Lobby TV']);

    $this->actingAs($user)
        ->post(route('app.screens.location', $screen), [
            'location_id' => $location->id,
        ])
        ->assertRedirect();

    expect($screen->fresh()->location_id)->toBe($location->id);

    $this->actingAs($user)
        ->get(route('app.locations.show', $location))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('screens', 1)
            ->where('screens.0.name', 'Lobby TV')
        );
});

test('location manager cannot access an unassigned location', function () {
    [$owner, $workspace] = locationUser();
    $manager = User::factory()->create();
    attachWorkspace($manager, $workspace, WorkspaceRole::LocationManager);

    $assigned = Location::factory()
        ->forWorkspace($workspace)
        ->createdBy($owner)
        ->create(['name' => 'Assigned Site']);
    $assigned->managers()->attach($manager->id);

    $other = Location::factory()
        ->forWorkspace($workspace)
        ->createdBy($owner)
        ->create(['name' => 'Other Site']);

    $this->actingAs($manager)
        ->get(route('app.locations.show', $assigned))
        ->assertOk();

    $this->actingAs($manager)
        ->get(route('app.locations.show', $other))
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(route('app.locations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/locations/index')
            ->has('locations.data', 1)
            ->where('locations.data.0.name', 'Assigned Site')
        );
});

test('owner can access every location in the workspace', function () {
    [$owner, $workspace] = locationUser();

    $one = Location::factory()->forWorkspace($workspace)->createdBy($owner)->create();
    $two = Location::factory()->forWorkspace($workspace)->createdBy($owner)->create();

    $this->actingAs($owner)
        ->get(route('app.locations.show', $one))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('app.locations.show', $two))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('app.locations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('locations.data', 2)
        );
});

test('location manager only sees screens at assigned locations', function () {
    [$owner, $workspace] = locationUser();
    $manager = User::factory()->create();
    attachWorkspace($manager, $workspace, WorkspaceRole::LocationManager);

    $assigned = Location::factory()
        ->forWorkspace($workspace)
        ->createdBy($owner)
        ->create();
    $assigned->managers()->attach($manager->id);

    $other = Location::factory()
        ->forWorkspace($workspace)
        ->createdBy($owner)
        ->create();

    $visible = Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($owner)
        ->create(['name' => 'Visible TV', 'location_id' => $assigned->id]);

    $hidden = Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($owner)
        ->create(['name' => 'Hidden TV', 'location_id' => $other->id]);

    $this->actingAs($manager)
        ->get(route('app.screens'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('screens', 1)
            ->where('screens.0.name', 'Visible TV')
        );

    $this->actingAs($manager)
        ->get(route('app.screens.show', $visible))
        ->assertOk();

    $this->actingAs($manager)
        ->get(route('app.screens.show', $hidden))
        ->assertForbidden();
});

test('cannot delete a location that still has screens', function () {
    [$user, $workspace] = locationUser();

    $location = Location::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create();

    Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create(['location_id' => $location->id]);

    $this->actingAs($user)
        ->delete(route('app.locations.destroy', $location))
        ->assertRedirect()
        ->assertSessionHasErrors('location');

    expect(Location::query()->whereKey($location->id)->exists())->toBeTrue();
});

test('owner can archive a location', function () {
    [$user, $workspace] = locationUser();

    $location = Location::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create();

    $this->actingAs($user)
        ->post(route('app.locations.archive', $location))
        ->assertRedirect(route('app.locations'));

    expect($location->fresh()->archived_at)->not->toBeNull();
});
