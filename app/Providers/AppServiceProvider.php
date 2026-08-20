<?php

namespace App\Providers;

use App\Operations\Aftermath\FinalizeDatabaseAftermath;
use App\Operations\Aftermath\FinalizeSiteAftermath;
use App\Support\StepAftermathRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag([
            FinalizeSiteAftermath::class,
            FinalizeDatabaseAftermath::class,
        ], 'operation.step_aftermaths');

        $this->app->singleton(StepAftermathRegistry::class, function ($app): StepAftermathRegistry {
            return new StepAftermathRegistry($app->tagged('operation.step_aftermaths'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureHttp();
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

    protected function configureHttp(): void
    {
        Http::macro('github', function (string $token) {
            return Http::baseUrl('https://api.github.com')
                ->acceptJson()
                ->withToken($token)
                ->timeout(10)
                ->connectTimeout(3);
        });
    }
}
