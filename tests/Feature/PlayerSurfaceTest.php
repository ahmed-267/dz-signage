<?php

test('the player page loads successfully without authentication', function () {
    $this->get(route('player'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('player/index'));
});

test('the player page is a standalone surface and not the customer dashboard', function () {
    $response = $this->get(route('player'))->assertOk();

    $response->assertInertia(fn ($page) => $page->component('player/index'));

    // Guests reach the player; customer dashboard remains protected.
    $this->get(route('app.dashboard'))->assertRedirect(route('login'));
});
