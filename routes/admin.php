<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Super Admin application (/admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::redirect('/', '/admin/dashboard');

        Route::inertia('dashboard', 'admin/dashboard')->name('dashboard');

        $placeholders = [
            'workspaces' => 'Workspaces',
            'users' => 'Users',
            'screens' => 'Screens',
            'trials' => 'Trials',
            'subscriptions' => 'Subscriptions',
            'plans' => 'Plans',
            'payments' => 'Payments',
            'invoices' => 'Invoices',
            'templates' => 'Templates',
            'categories' => 'Categories',
            'widgets' => 'Widgets',
            'media' => 'Media',
            'screen-health' => 'Screen Health',
            'publishing-jobs' => 'Publishing Jobs',
            'scheduling-jobs' => 'Scheduling Jobs',
            'errors' => 'Errors',
            'system-health' => 'System Health',
            'tickets' => 'Tickets',
            'announcements' => 'Announcements',
            'audit-logs' => 'Audit Logs',
            'settings' => 'Platform Settings',
        ];

        foreach ($placeholders as $uri => $title) {
            Route::get($uri, fn () => Inertia::render('admin/coming-soon', [
                'title' => $title,
                'path' => '/admin/'.$uri,
            ]))->name(str_replace('-', '_', $uri));
        }
    });
