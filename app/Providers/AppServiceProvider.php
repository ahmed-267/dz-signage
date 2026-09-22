<?php

namespace App\Providers;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Workspace;
use App\Support\Widgets\Weather\OpenMeteoWeatherProvider;
use App\Support\Widgets\Weather\WeatherProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WeatherProvider::class, OpenMeteoWeatherProvider::class);

        // Cashier auto-registers POST {cashier.path}/webhook before application
        // routes are loaded, so the webhook is taken over by resolving our
        // subclass out of the container instead of re-declaring the route.
        $this->app->bind(CashierWebhookController::class, StripeWebhookController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // The Workspace is the billable customer, not the User.
        Cashier::useCustomerModel(Workspace::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
