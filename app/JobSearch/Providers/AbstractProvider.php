<?php

namespace App\JobSearch\Providers;

use App\JobSearch\JobQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class AbstractProvider implements JobProvider
{
    protected function config(string $key, mixed $default = null): mixed
    {
        return config("jobsearch.providers.{$this->key()}.{$key}", $default);
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('jobsearch.http_timeout', 20))
            ->retry(2, 750, throw: false)
            ->withUserAgent(config('app.name', 'ATS Boost').' job search');
    }

    protected function ensureOk(Response $response): Response
    {
        if (! $response->successful()) {
            throw new RuntimeException("{$this->label()} respondió HTTP {$response->status()}");
        }

        return $response;
    }

    /**
     * Para portales sin búsqueda por texto del lado del servidor.
     */
    protected function matchesKeywords(JobQuery $query, string $title, string $description = ''): bool
    {
        $tokens = $query->keywordTokens();

        if ($tokens === []) {
            return true;
        }

        $title = mb_strtolower($title);
        $description = mb_strtolower($description);

        foreach ($tokens as $token) {
            if (str_contains($title, $token)) {
                return true;
            }
        }

        foreach ($tokens as $token) {
            if (! str_contains($description, $token)) {
                return false;
            }
        }

        return true;
    }
}
