<?php

namespace App\JobSearch\Providers;

use App\JobSearch\JobPosting;
use App\JobSearch\JobQuery;
use App\JobSearch\Regions;
use Illuminate\Support\Facades\Cache;

/**
 * Arbeitnow: bolsa europea (foco Alemania / DACH), incluye ofertas en inglés
 * y con visa. API pública sin búsqueda por texto: se filtra localmente.
 */
class ArbeitnowProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'arbeitnow';
    }

    public function label(): string
    {
        return 'Arbeitnow';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supports(JobQuery $query): bool
    {
        return $query->region() === Regions::EUROPE;
    }

    public function search(JobQuery $query): array
    {
        $postings = [];
        $pages = (int) $this->config('pages', 3);

        for ($page = 1; $page <= $pages; $page++) {
            $data = Cache::remember(
                "jobsearch:arbeitnow:page:{$page}",
                now()->addHour(),
                fn () => $this->ensureOk(
                    $this->http()->get('https://www.arbeitnow.com/api/job-board-api', ['page' => $page])
                )->json('data', [])
            );

            if ($data === []) {
                break;
            }

            foreach ($data as $job) {
                $description = JobPosting::cleanText($job['description'] ?? '');

                if (! $this->matchesKeywords($query, (string) ($job['title'] ?? ''), $description)) {
                    continue;
                }

                $postings[] = new JobPosting(
                    source: $this->key(),
                    externalId: (string) ($job['slug'] ?? md5($job['url'] ?? '')),
                    title: (string) ($job['title'] ?? ''),
                    company: (string) ($job['company_name'] ?? ''),
                    url: (string) ($job['url'] ?? ''),
                    description: $description,
                    location: $job['location'] ?? null,
                    countryCode: Regions::detectCountry($job['location'] ?? null) ?? 'de',
                    isRemote: (bool) ($job['remote'] ?? false),
                    remoteZone: ($job['remote'] ?? false) ? 'Europe' : null,
                    postedAt: JobPosting::parseDate($job['created_at'] ?? null),
                    employmentType: implode(', ', (array) ($job['job_types'] ?? [])) ?: null,
                );
            }
        }

        return array_values(array_filter($postings, fn (JobPosting $p) => $p->title !== '' && $p->url !== ''));
    }
}
