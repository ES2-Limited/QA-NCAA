<?php

namespace App\Providers;

use App\Contracts\AiService as AiServiceContract;
use App\Services\AiServiceInterface;
use App\Services\FakeAiService;
use App\Services\GeminiAiService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiServiceContract::class, function () {
            $key = config('services.gemini.key');

            return ! empty($key)
                ? new GeminiAiService
                : new FakeAiService;
        });

        $this->app->singleton(AiServiceInterface::class, function ($app) {
            return $app->make(AiServiceContract::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
