<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BillingPlanController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PlatformErrorController;
use App\Http\Controllers\Admin\PlatformSettingController;
use App\Http\Controllers\Admin\PublishingJobsController;
use App\Http\Controllers\Admin\ScreenController;
use App\Http\Controllers\Admin\ScreenHealthController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SupportRequestController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform administration (/admin) — Super Admin & Admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('home');
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces');
        Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
        Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::match(['patch', 'post'], 'users/{user}/platform-role', [UserController::class, 'updatePlatformRole'])
            ->name('users.platform_role');

        Route::get('templates', [TemplateController::class, 'index'])->name('templates');
        Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::get('templates/{template}/builder', [TemplateController::class, 'edit'])->name('templates.builder');
        Route::patch('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::post('templates/{template}/publish', [TemplateController::class, 'publish'])->name('templates.publish');
        Route::post('templates/{template}/archive', [TemplateController::class, 'archive'])->name('templates.archive');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

        Route::get('screens', [ScreenController::class, 'index'])->name('screens');
        Route::get('screens/{screen}', [ScreenController::class, 'show'])->name('screens.show');
        Route::get('screen-health', [ScreenHealthController::class, 'index'])->name('screen_health');

        Route::get('publishing-jobs', [PublishingJobsController::class, 'index'])->name('publishing_jobs');
        Route::get('publishing-jobs/{deployment}', [PublishingJobsController::class, 'show'])->name('publishing_jobs.show');

        Route::get('system-health', [SystemHealthController::class, 'index'])->name('system_health');

        Route::get('errors', [PlatformErrorController::class, 'index'])->name('errors');
        Route::post('errors/{error}/resolve', [PlatformErrorController::class, 'resolve'])->name('errors.resolve');

        Route::get('support', [SupportRequestController::class, 'index'])->name('support');
        Route::get('support/{support}', [SupportRequestController::class, 'show'])->name('support.show');
        Route::patch('support/{support}', [SupportRequestController::class, 'update'])->name('support.update');
        Route::post('support/{support}/notes', [SupportRequestController::class, 'storeNote'])->name('support.notes.store');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit_log');

        Route::get('feature-flags', [FeatureFlagController::class, 'index'])->name('feature_flags');
        Route::patch('feature-flags/{featureFlag}', [FeatureFlagController::class, 'update'])
            ->name('feature_flags.update');

        Route::get('settings', [PlatformSettingController::class, 'index'])->name('settings');
        Route::patch('settings', [PlatformSettingController::class, 'update'])->name('settings.update');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions');
        Route::get('subscriptions/plans', [BillingPlanController::class, 'index'])
            ->name('subscriptions.plans');
        Route::get('subscriptions/plans/{plan}/edit', [BillingPlanController::class, 'edit'])
            ->name('subscriptions.plans.edit');
        Route::match(['put', 'patch'], 'subscriptions/plans/{plan}', [BillingPlanController::class, 'update'])
            ->name('subscriptions.plans.update');
        Route::post('subscriptions/plans/{plan}/sync-stripe-price', [BillingPlanController::class, 'syncStripePrice'])
            ->name('subscriptions.plans.sync_stripe_price');
        Route::post('subscriptions/plans/{plan}/migrate-subscribers', [BillingPlanController::class, 'migrateSubscribers'])
            ->name('subscriptions.plans.migrate_subscribers');
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::patch('subscriptions/{subscription}', [SubscriptionController::class, 'update'])
            ->name('subscriptions.update');
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices');

        // Compatibility redirects for earlier placeholder URIs
        Route::redirect('tickets', '/admin/support');
        Route::redirect('audit-logs', '/admin/audit-log');
        Route::redirect('trials', '/admin/workspaces');
        Route::redirect('plans', '/admin/subscriptions/plans');
        Route::redirect('payments', '/admin/invoices');
        Route::redirect('categories', '/admin/templates');
        Route::redirect('widgets', '/admin/templates');
        Route::redirect('media', '/admin/templates');
        Route::redirect('scheduling-jobs', '/admin/publishing-jobs');
        Route::redirect('announcements', '/admin/support');
    });
