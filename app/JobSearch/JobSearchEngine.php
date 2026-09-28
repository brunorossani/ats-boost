<?php

namespace App\JobSearch;

use App\JobSearch\Providers\JobProvider;
use Illuminate\Support\Facades\Log;

class JobSearchEngine
{
    /**
     * @param  iterable<JobProvider>  $providers
     */
    public function __construct(private iterable $providers) {}

    public static function fromConfig(): self
    {
        return new self(array_map(
            fn (string $class) => app($class),
            (array) config('jobsearch.enabled_providers', [])
        ));
    }

    public function search(JobQuery $query): SearchResult
    {
        $collected = [];
        $report = [];

        foreach ($this->providers as $provider) {
            if (! $provider->supports($query)) {
                continue;
            }

            if (! $provider->isConfigured()) {
                $report[$provider->key()] = ['label' => $provider->label(), 'status' => 'not_configured', 'found' => 0];

                continue;
            }

            try {
                $postings = $provider->search($query);
                $report[$provider->key()] = ['label' => $provider->label(), 'status' => 'ok', 'found' => count($postings)];
                array_push($collected, ...$postings);
            } catch (\Throwable $e) {
                Log::warning('JobSearchEngine: proveedor falló', [
                    'provider' => $provider->key(),
                    'error' => $e->getMessage(),
                ]);
                $report[$provider->key()] = ['label' => $provider->label(), 'status' => 'error', 'found' => 0, 'error' => $e->getMessage()];
            }
        }

        $cutoff = now()->subDays(max(1, $query->maxAgeDays))->startOfDay();

        $postings = collect($collected)
            ->filter(fn (JobPosting $p) => preg_match('#^https?://#i', $p->url))
            ->filter(fn (JobPosting $p) => $p->postedAt === null || $p->postedAt->greaterThanOrEqualTo($cutoff))
            ->filter(fn (JobPosting $p) => $this->matchesLocation($p, $query))
            ->groupBy(fn (JobPosting $p) => $p->fingerprint())
            ->map(fn ($duplicates) => $this->pickBest($duplicates->all()))
            ->sortByDesc(fn (JobPosting $p) => $p->postedAt?->getTimestamp() ?? 0)
            ->take($query->limit)
            ->values()
            ->all();

        return new SearchResult($postings, $report);
    }

    private function matchesLocation(JobPosting $posting, JobQuery $query): bool
    {
        if ($posting->isRemote) {
            return $query->includeRemote && Regions::remoteZoneAccepts($posting->remoteZone, $query->countryCode);
        }

        if ($query->remoteOnly()) {
            return false;
        }

        // Sin país detectado confiamos en el filtro del portal, que ya buscó por ubicación.
        return $posting->countryCode === null || $posting->countryCode === strtolower($query->countryCode);
    }

    /**
     * Entre duplicados de distintos portales: la fuente directa de la empresa
     * primero, después la descripción más completa, después la más reciente.
     *
     * @param  list<JobPosting>  $duplicates
     */
    private function pickBest(array $duplicates): JobPosting
    {
        $priority = array_flip((array) config('jobsearch.source_priority', []));

        usort($duplicates, fn (JobPosting $a, JobPosting $b) => [
            $priority[$a->source] ?? 99,
            -mb_strlen($a->description),
            -($a->postedAt?->getTimestamp() ?? 0),
        ] <=> [
            $priority[$b->source] ?? 99,
            -mb_strlen($b->description),
            -($b->postedAt?->getTimestamp() ?? 0),
        ]);

        return $duplicates[0];
    }
}
