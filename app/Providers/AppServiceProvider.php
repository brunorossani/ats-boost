<?php

namespace App\Providers;

use Anthropic\Client;
use App\JobSearch\JobSearchEngine;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(JobSearchEngine::class, fn () => JobSearchEngine::fromConfig());

        $this->app->singleton(Client::class, function (): Client {
            $apiKey = (string) config('services.anthropic.api_key');

            if ($apiKey === '') {
                throw new RuntimeException('Falta ANTHROPIC_API_KEY en el .env.');
            }

            return new Client(
                apiKey: $apiKey,
                requestOptions: ['timeout' => (float) config('resume.request_timeout', 120), 'maxRetries' => 1],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
