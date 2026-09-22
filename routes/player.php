<?php

use App\Http\Controllers\Player\HeartbeatController;
use App\Http\Controllers\Player\ManifestController;
use App\Http\Controllers\Player\MediaController;
use App\Http\Controllers\Player\OfflinePackageController;
use App\Http\Controllers\Player\PairingController;
use App\Http\Controllers\Player\PlaybackEventController;
use App\Http\Controllers\Player\WidgetDataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Screen player (/player)
|--------------------------------------------------------------------------
|
| Standalone full-screen surface — no Customer or Admin chrome.
|
*/

Route::get('/player', [PairingController::class, 'index'])->name('player');

Route::prefix('player/api')
    ->name('player.api.')
    ->group(function () {
        Route::post('pairing-sessions', [PairingController::class, 'store'])
            ->middleware('throttle:player-pairing')
            ->name('pairing_sessions.store');

        Route::get('pairing-sessions/{publicId}', [PairingController::class, 'show'])
            ->middleware('throttle:player-pairing-poll')
            ->name('pairing_sessions.show');

        Route::middleware('player.device')->group(function () {
            Route::post('heartbeat', [HeartbeatController::class, 'store'])
                ->middleware('throttle:player-heartbeat')
                ->name('heartbeat');
            Route::post('playback-events', [PlaybackEventController::class, 'store'])
                ->middleware('throttle:player-playback-events')
                ->name('playback_events');
            Route::get('manifest/check', [ManifestController::class, 'check'])
                ->name('manifest.check');
            Route::get('manifest', [ManifestController::class, 'show'])
                ->name('manifest');
            Route::get('offline-package', [OfflinePackageController::class, 'show'])
                ->name('offline_package');
            Route::post('widgets/data', [WidgetDataController::class, 'store'])
                ->middleware('throttle:player-widget-data')
                ->name('widgets.data');
            Route::get('media/{mediaAsset}', [MediaController::class, 'show'])
                ->name('media.show');
        });
    });
