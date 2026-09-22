<?php

use App\Http\Middleware\AuthenticatePlayerDevice;
use App\Http\Middleware\EnsureHasWorkspace;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfHasWorkspace;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'dz_player_device_token']);

        $middleware->validateCsrfTokens(except: [
            'player/api/*',
            // Cashier's Stripe webhook (config/cashier.php `path`) is verified
            // by signature, not by session CSRF.
            'stripe/*',
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'workspace' => EnsureHasWorkspace::class,
            'onboarding' => RedirectIfHasWorkspace::class,
            'player.device' => AuthenticatePlayerDevice::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('player/api/*')
                || $request->expectsJson(),
        );
    })->create();
