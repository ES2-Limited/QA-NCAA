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
        $this->app->singleton(AiServiceInterface::class, function () {
            $apiKey = env('GEMINI_API_KEY');
            if (empty($apiKey)) {
                return new FakeAiService;
            }

            $model = env('GEMINI_MODEL', 'gemini-1.5-flash');

            return new GeminiAiService($apiKey, $model);
        });

        $this->app->bind(AiServiceContract::class, FakeAiService::class);
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
