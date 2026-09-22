<?php

use App\Http\Controllers\App\AiController;
use App\Http\Controllers\App\AnalyticsController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\BrandKitController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\DeploymentController;
use App\Http\Controllers\App\HelpController;
use App\Http\Controllers\App\LocationController;
use App\Http\Controllers\App\MediaController;
use App\Http\Controllers\App\PlaylistController;
use App\Http\Controllers\App\PublishingController;
use App\Http\Controllers\App\ScheduleController;
use App\Http\Controllers\App\ScreenController;
use App\Http\Controllers\App\ScreenDesignController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\TeamController;
use App\Http\Controllers\App\TemplateController;
use App\Http\Controllers\App\WidgetDataController;
use App\Http\Controllers\App\WorkspaceController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer application (/app)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'workspace'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::redirect('/', '/app/dashboard');

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
        Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');

        Route::get('workspace-settings', [WorkspaceController::class, 'edit'])->name('workspace_settings.edit');
        Route::post('workspace-settings', [WorkspaceController::class, 'update'])->name('workspace_settings.update');

        Route::get('settings', [SettingsController::class, 'show'])->name('settings');
        Route::get('settings/security', [SettingsController::class, 'security'])
            ->middleware(RequirePassword::class)
            ->name('settings.security');
        Route::get('settings/{tab}', [SettingsController::class, 'show'])
            ->whereIn('tab', ['general', 'workspace', 'billing'])
            ->name('settings.tab');

        Route::get('team', [TeamController::class, 'index'])->name('team');
        Route::post('team/invitations', [TeamController::class, 'invite'])->name('team.invite');
        Route::patch('team/members/{member}', [TeamController::class, 'updateRole'])->name('team.members.update');
        Route::delete('team/members/{member}', [TeamController::class, 'destroy'])->name('team.members.destroy');
        Route::post('team/leave', [TeamController::class, 'leave'])->name('team.leave');
        Route::post('team/invitations/{invitation}/resend', [TeamController::class, 'resendInvitation'])->name('team.invitations.resend');
        Route::post('team/invitations/{invitation}/revoke', [TeamController::class, 'revokeInvitation'])->name('team.invitations.revoke');

        Route::get('media', [MediaController::class, 'index'])->name('media');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::post('media/{media}/replace', [MediaController::class, 'replace'])->name('media.replace');
        Route::post('media/{media}/duplicate', [MediaController::class, 'duplicate'])->name('media.duplicate');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
        Route::get('media/{media}/download', [MediaController::class, 'download'])->name('media.download');

        Route::prefix('ai')->name('ai.')->middleware('throttle:ai-generation')->group(function () {
            Route::get('status', [AiController::class, 'status'])->name('status');
            Route::get('history', [AiController::class, 'history'])->name('history');
            Route::post('text', [AiController::class, 'generateText'])->name('text');
            Route::post('text/rewrite', [AiController::class, 'rewriteText'])->name('text.rewrite');
            Route::post('image', [AiController::class, 'generateImage'])->name('image');
            Route::post('image/{generation}/save', [AiController::class, 'saveImage'])->name('image.save');
            Route::post('design', [AiController::class, 'generateDesign'])->name('design');
            Route::post('design/accept', [AiController::class, 'acceptDesign'])->name('design.accept');
            Route::post('agent/propose', [AiController::class, 'proposeAgent'])->name('agent.propose');
            Route::post('agent/confirm', [AiController::class, 'confirmAgent'])->name('agent.confirm');
            Route::get('generations/{generation}/preview', [AiController::class, 'preview'])->name('generations.preview');
        });

        Route::get('templates', [TemplateController::class, 'index'])->name('templates');
        Route::get('templates/{template}/preview', [TemplateController::class, 'preview'])->name('templates.preview');
        Route::post('templates/{template}/favourite', [TemplateController::class, 'toggleFavourite'])->name('templates.favourite');
        Route::post('templates/{template}/use', [ScreenDesignController::class, 'storeFromTemplate'])->name('templates.use');

        Route::get('screen-designs', [ScreenDesignController::class, 'index'])->name('screen_designs');
        Route::post('screen-designs/blank', [ScreenDesignController::class, 'storeBlank'])->name('screen_designs.store_blank');
        Route::post('screen-designs/bulk-destroy', [ScreenDesignController::class, 'bulkDestroy'])->name('screen_designs.bulk_destroy');
        Route::get('screen-designs/{screenDesign}/edit', [ScreenDesignController::class, 'edit'])->name('screen_designs.edit');
        Route::get('screen-designs/{screenDesign}/preview', [ScreenDesignController::class, 'preview'])->name('screen_designs.preview');
        Route::patch('screen-designs/{screenDesign}', [ScreenDesignController::class, 'update'])->name('screen_designs.update');
        Route::post('screen-designs/{screenDesign}/publish', [ScreenDesignController::class, 'publish'])->name('screen_designs.publish');
        Route::post('screen-designs/{screenDesign}/duplicate', [ScreenDesignController::class, 'duplicate'])->name('screen_designs.duplicate');
        Route::post('screen-designs/{screenDesign}/rename', [ScreenDesignController::class, 'rename'])->name('screen_designs.rename');
        Route::delete('screen-designs/{screenDesign}', [ScreenDesignController::class, 'destroy'])->name('screen_designs.destroy');
        Route::post('screen-designs/{screenDesign}/publish-to-screens', [DeploymentController::class, 'publishToScreens'])
            ->name('screen_designs.publish_to_screens');

        Route::get('playlists', [PlaylistController::class, 'index'])->name('playlists');
        Route::get('playlists/published-designs', [PlaylistController::class, 'publishedDesigns'])
            ->name('playlists.published_designs');
        Route::post('playlists', [PlaylistController::class, 'store'])->name('playlists.store');
        Route::post('playlists/bulk-destroy', [PlaylistController::class, 'bulkDestroy'])->name('playlists.bulk_destroy');
        Route::get('playlists/{playlist}/edit', [PlaylistController::class, 'edit'])->name('playlists.edit');
        Route::get('playlists/{playlist}/preview', [PlaylistController::class, 'preview'])->name('playlists.preview');
        Route::patch('playlists/{playlist}', [PlaylistController::class, 'update'])->name('playlists.update');
        Route::post('playlists/{playlist}/publish', [PlaylistController::class, 'publish'])->name('playlists.publish');
        Route::post('playlists/{playlist}/duplicate', [PlaylistController::class, 'duplicate'])->name('playlists.duplicate');
        Route::post('playlists/{playlist}/archive', [PlaylistController::class, 'archive'])->name('playlists.archive');
        Route::delete('playlists/{playlist}', [PlaylistController::class, 'destroy'])->name('playlists.destroy');
        Route::post('playlists/{playlist}/publish-to-screens', [PlaylistController::class, 'publishToScreens'])
            ->name('playlists.publish_to_screens');

        Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules');
        Route::get('schedules/create', [ScheduleController::class, 'create'])->name('schedules.create');
        Route::post('schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::post('schedules/bulk-destroy', [ScheduleController::class, 'bulkDestroy'])->name('schedules.bulk_destroy');
        Route::get('schedules/{schedule}/edit', [ScheduleController::class, 'edit'])->name('schedules.edit');
        Route::get('schedules/{schedule}/preview', [ScheduleController::class, 'preview'])->name('schedules.preview');
        Route::patch('schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
        Route::post('schedules/{schedule}/activate', [ScheduleController::class, 'activate'])->name('schedules.activate');
        Route::post('schedules/{schedule}/pause', [ScheduleController::class, 'pause'])->name('schedules.pause');
        Route::post('schedules/{schedule}/duplicate', [ScheduleController::class, 'duplicate'])->name('schedules.duplicate');
        Route::post('schedules/{schedule}/archive', [ScheduleController::class, 'archive'])->name('schedules.archive');
        Route::post('schedules/preview-conflicts', [ScheduleController::class, 'previewConflicts'])->name('schedules.preview_conflicts');
        Route::post('schedules/{schedule}/conflicts', [ScheduleController::class, 'conflicts'])->name('schedules.conflicts');
        Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');

        Route::get('screens', [ScreenController::class, 'index'])->name('screens');
        Route::redirect('tvs', '/app/screens')->name('tvs');
        Route::get('screens/pair/{publicId}', [ScreenController::class, 'pairShow'])->name('screens.pair.show');
        Route::post('screens/pair', [ScreenController::class, 'storePair'])
            ->middleware('throttle:screen-pair-claim')
            ->name('screens.pair.store');
        Route::post('screens/pair/{publicId}', [ScreenController::class, 'storePairByPublicId'])
            ->middleware('throttle:screen-pair-claim')
            ->name('screens.pair.claim');
        Route::get('screens/{screen}', [ScreenController::class, 'show'])->name('screens.show');
        Route::post('screens/{screen}/rename', [ScreenController::class, 'rename'])->name('screens.rename');
        Route::post('screens/{screen}/location', [ScreenController::class, 'updateLocation'])->name('screens.location');
        Route::post('screens/{screen}/status', [ScreenController::class, 'setStatus'])->name('screens.status');
        Route::post('screens/bulk-status', [ScreenController::class, 'bulkStatus'])->name('screens.bulk_status');
        Route::post('screens/bulk-publish', [ScreenController::class, 'bulkPublish'])->name('screens.bulk_publish');
        Route::post('screens/{screen}/publish', [ScreenController::class, 'publishContent'])->name('screens.publish');
        Route::delete('screens/{screen}', [ScreenController::class, 'destroy'])->name('screens.destroy');

        Route::get('locations', [LocationController::class, 'index'])->name('locations');
        Route::get('locations/create', [LocationController::class, 'create'])->name('locations.create');
        Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
        Route::get('locations/{location}', [LocationController::class, 'show'])->name('locations.show');
        Route::get('locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
        Route::patch('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::post('locations/{location}/archive', [LocationController::class, 'archive'])->name('locations.archive');
        Route::post('locations/{location}/screens', [LocationController::class, 'assignScreen'])->name('locations.assign_screen');
        Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');

        Route::get('publishing', [PublishingController::class, 'index'])->name('publishing');
        Route::post('publishing', [PublishingController::class, 'store'])->name('publishing.store');
        Route::post('publishing/{deployment}/republish', [PublishingController::class, 'republish'])
            ->name('publishing.republish');

        Route::post('widgets/data', [WidgetDataController::class, 'store'])
            ->middleware('throttle:widget-data')
            ->name('widgets.data');

        Route::get('help', [HelpController::class, 'show'])->name('help');
        Route::post('help', [HelpController::class, 'store'])->name('help.store');

        Route::get('billing', [BillingController::class, 'show'])->name('billing');
        Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::post('billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
        Route::patch('billing/quantity', [BillingController::class, 'updateQuantity'])->name('billing.quantity');
        Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        Route::post('billing/resume', [BillingController::class, 'resume'])->name('billing.resume');

        Route::get('analytics', AnalyticsController::class)->name('analytics');

        Route::get('brand-kit', [BrandKitController::class, 'show'])->name('brand_kit');
        Route::put('brand-kit/{brandKit}', [BrandKitController::class, 'update'])->name('brand_kit.update');
    });
