<?php

namespace App\JobSearch\Providers;

use App\JobSearch\JobPosting;
use App\JobSearch\JobQuery;
use App\JobSearch\Regions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Ofertas directo de la página de empleos de cada empresa (Greenhouse, Lever,
 * Ashby). Es la fuente más fresca: la oferta aparece el mismo día que se publica,
 * antes de que la levanten los agregadores. Las empresas se configuran en
 * config/jobsearch.php.
 */
class AtsBoardsProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'ats';
    }

    public function label(): string
    {
        return 'Páginas de empleo de empresas';
    }

    public function isConfigured(): bool
    {
        return $this->companies() !== [];
    }

    public function supports(JobQuery $query): bool
    {
        return true;
    }

    public function search(JobQuery $query): array
    {
        $postings = [];

        foreach ($this->companies() as $company) {
            try {
                $jobs = Cache::remember(
                    "jobsearch:ats:{$company['ats']}:{$company['slug']}",
                    now()->addHour(),
                    fn () => match ($company['ats']) {
                        'greenhouse' => $this->greenhouse($company),
                        'lever' => $this->lever($company),
                        'ashby' => $this->ashby($company),
                        default => [],
                    }
                );
            } catch (\Throwable $e) {
                Log::warning('AtsBoardsProvider: falló una empresa', ['company' => $company, 'error' => $e->getMessage()]);

                continue;
            }

            foreach ($jobs as $posting) {
                if ($this->matchesKeywords($query, $posting->title, $posting->description)) {
                    $postings[] = $posting;
                }
            }
        }

        return $postings;
    }

    /**
     * @return list<array{ats: string, slug: string, name?: string}>
     */
    private function companies(): array
    {
        return array_values(array_filter(
            (array) config('jobsearch.providers.ats.companies', []),
            fn ($c) => is_array($c) && isset($c['ats'], $c['slug'])
        ));
    }

    /** @return list<JobPosting> */
    private function greenhouse(array $company): array
    {
        $data = $this->ensureOk(
            $this->http()->get("https://boards-api.greenhouse.io/v1/boards/{$company['slug']}/jobs", ['content' => 'true'])
        )->json('jobs', []);

        return array_map(fn (array $job) => $this->posting(
            id: "greenhouse:{$company['slug']}:{$job['id']}",
            title: $job['title'] ?? '',
            company: $job['company_name'] ?? $company['name'] ?? $company['slug'],
            url: $job['absolute_url'] ?? '',
            description: $job['content'] ?? '',
            location: data_get($job, 'location.name'),
            postedAt: $job['first_published'] ?? $job['updated_at'] ?? null,
        ), $data);
    }

    /** @return list<JobPosting> */
    private function lever(array $company): array
    {
        $data = $this->ensureOk(
            $this->http()->get("https://api.lever.co/v0/postings/{$company['slug']}", ['mode' => 'json'])
        )->json();

        return array_map(fn (array $job) => $this->posting(
            id: "lever:{$company['slug']}:{$job['id']}",
            title: $job['text'] ?? '',
            company: $company['name'] ?? $company['slug'],
            url: $job['hostedUrl'] ?? '',
            description: trim(($job['descriptionPlain'] ?? '')."\n\n".collect($job['lists'] ?? [])
                ->map(fn ($l) => ($l['text'] ?? '')."\n".($l['content'] ?? ''))->implode("\n\n")),
            location: data_get($job, 'categories.location'),
            postedAt: $job['createdAt'] ?? null,
            remote: ($job['workplaceType'] ?? null) === 'remote',
            employmentType: data_get($job, 'categories.commitment'),
        ), is_array($data) ? $data : []);
    }

    /** @return list<JobPosting> */
    private function ashby(array $company): array
    {
        $data = $this->ensureOk(
            $this->http()->get("https://api.ashbyhq.com/posting-api/job-board/{$company['slug']}")
        )->json('jobs', []);

        return array_map(fn (array $job) => $this->posting(
            id: "ashby:{$company['slug']}:{$job['id']}",
            title: $job['title'] ?? '',
            company: $company['name'] ?? $company['slug'],
            url: $job['jobUrl'] ?? '',
            description: $job['descriptionPlain'] ?? $job['descriptionHtml'] ?? '',
            location: $job['location'] ?? null,
            postedAt: $job['publishedAt'] ?? null,
            remote: (bool) ($job['isRemote'] ?? false),
            employmentType: $job['employmentType'] ?? null,
        ), $data);
    }

    private function posting(
        string $id,
        string $title,
        string $company,
        string $url,
        string $description,
        ?string $location,
        mixed $postedAt,
        ?bool $remote = null,
        ?string $employmentType = null,
    ): JobPosting {
        $remote ??= (bool) preg_match('/\b(remote|remoto|anywhere)\b/i', (string) $location);

        return new JobPosting(
            source: $this->key(),
            externalId: $id,
            title: $title,
            company: $company,
            url: $url,
            description: JobPosting::cleanText($description),
            location: $location,
            countryCode: Regions::detectCountry($location),
            isRemote: $remote,
            remoteZone: $remote ? $location : null,
            postedAt: JobPosting::parseDate($postedAt),
            employmentType: $employmentType,
        );
    }
}
