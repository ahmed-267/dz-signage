<?php

test('landing seo description mentions RMSignage Workspace', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('seo.description', fn (string $description) => str_contains($description, 'RMSignage Workspace'))
        );
});
