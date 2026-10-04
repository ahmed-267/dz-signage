<?php

use App\Http\Controllers\DigitalAssetLinksController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Onboarding\OnboardingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/

Route::get('/', LandingController::class)->name('home');

Route::get('/player.webmanifest', function () {
    $path = public_path('player.webmanifest');

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/manifest+json; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('player.manifest');

Route::get('/player-sw.js', function () {
    $path = public_path('player-sw.js');

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'no-cache',
        'Service-Worker-Allowed' => '/',
    ]);
})->name('player.service-worker');

Route::get('.well-known/assetlinks.json', DigitalAssetLinksController::class)
    ->name('well-known.assetlinks');

Route::get('/manifest.webmanifest', function () {
    $path = public_path('manifest.webmanifest');

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/manifest+json; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    $path = public_path('sw.js');

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'no-cache',
        'Service-Worker-Allowed' => '/app/',
    ]);
})->name('pwa.service-worker');

Route::get('/health', [HealthController::class, 'live'])->name('health');
Route::get('/ready', [HealthController::class, 'ready'])->name('ready');

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
