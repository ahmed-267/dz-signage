<?php

test('the application container resolves core services', function () {
    expect(app()->isBooted())->toBeTrue();
    expect(app()->environment())->toBe('testing');
    expect(config('app.name'))->not->toBeEmpty();
});
