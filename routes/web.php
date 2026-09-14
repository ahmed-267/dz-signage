<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Onboarding\OnboardingController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified', 'onboarding'])
    ->prefix('onboarding')
    ->group(function () {
        Route::get('/', [OnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('/', [OnboardingController::class, 'store'])->name('onboarding.store');
    });

Route::get('/invitations/{token}', [InvitationController::class, 'show'])
    ->name('invitations.show');

Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware(['auth', 'verified'])
    ->name('invitations.accept');

/*
|--------------------------------------------------------------------------
| Application surfaces
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
require __DIR__.'/app.php';
require __DIR__.'/admin.php';
require __DIR__.'/player.php';
