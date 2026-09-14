<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Customer application (/app)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::redirect('/', '/app/dashboard');

        Route::inertia('dashboard', 'app/dashboard')->name('dashboard');

        $placeholders = [
            'screen-designs' => 'Screen Designs',
            'templates' => 'Templates',
            'media' => 'Media',
            'brand-kit' => 'Brand Kit',
            'playlists' => 'Playlists',
            'schedules' => 'Schedules',
            'publishing' => 'Publishing',
            'screens' => 'Screens',
            'locations' => 'Locations',
            'analytics' => 'Analytics',
            'team' => 'Team',
            'billing' => 'Billing',
        ];

        foreach ($placeholders as $uri => $title) {
            Route::get($uri, fn () => Inertia::render('app/coming-soon', [
                'title' => $title,
                'path' => '/app/'.$uri,
            ]))->name(str_replace('-', '_', $uri));
        }
    });
