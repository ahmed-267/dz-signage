<?php

use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\TeamController;
use App\Http\Controllers\App\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

        Route::get('team', [TeamController::class, 'index'])->name('team');
        Route::post('team/invitations', [TeamController::class, 'invite'])->name('team.invite');
        Route::patch('team/members/{member}', [TeamController::class, 'updateRole'])->name('team.members.update');
        Route::delete('team/members/{member}', [TeamController::class, 'destroy'])->name('team.members.destroy');
        Route::post('team/leave', [TeamController::class, 'leave'])->name('team.leave');
        Route::post('team/invitations/{invitation}/resend', [TeamController::class, 'resendInvitation'])->name('team.invitations.resend');
        Route::post('team/invitations/{invitation}/revoke', [TeamController::class, 'revokeInvitation'])->name('team.invitations.revoke');

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
            'billing' => 'Billing',
        ];

        foreach ($placeholders as $uri => $title) {
            Route::get($uri, fn () => Inertia::render('app/coming-soon', [
                'title' => $title,
                'path' => '/app/'.$uri,
            ]))->name(str_replace('-', '_', $uri));
        }
    });
