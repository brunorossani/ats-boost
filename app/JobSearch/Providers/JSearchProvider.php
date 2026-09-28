<?php

namespace App\JobSearch\Providers;

use App\JobSearch\JobPosting;
use App\JobSearch\JobQuery;

/**
 * Google for Jobs vía JSearch (RapidAPI / OpenWeb Ninja). Agrega LinkedIn, Indeed,
 * Glassdoor, Computrabajo, Bumeran, ZipRecruiter, etc. en todos los países.
 */
class JSearchProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'jsearch';
    }

    public function label(): string
    {
        return 'Google Jobs (JSearch)';
    }

    public function isConfigured(): bool
    {
        return filled($this->config('api_key'));
    }

    public function supports(JobQuery $query): bool
    {
        return true;
    }

    public function search(JobQuery $query): array
    {
        $host = $this->config('host', 'jsearch.p.rapidapi.com');

        $params = [
            'query' => trim($query->keywords.($query->locationText() ? " in {$query->locationText()}" : '')),
            'page' => 1,
            'num_pages' => max(1, min(5, (int) ceil($query->limit / 10))),
            'date_posted' => $this->datePosted($query->maxAgeDays),
        ];

        if ($query->countryCode) {
            $params['country'] = strtolower($query->countryCode);
        }

        if ($query->remoteOnly()) {
            $params['work_from_home'] = 'true';
        }

        $response = $this->ensureOk(
            $this->http()
                ->withHeaders([
                    'X-RapidAPI-Key' => $this->config('api_key'),
                    'X-RapidAPI-Host' => $host,
                ])
                ->get("https://{$host}/search", $params)
        );

        return collect($response->json('data', []))
            ->map(fn (array $job) => new JobPosting(
                source: $this->key(),
                externalId: (string) ($job['job_id'] ?? md5($job['job_apply_link'] ?? json_encode($job))),
                title: (string) ($job['job_title'] ?? ''),
                company: (string) ($job['employer_name'] ?? ''),
                url: (string) ($job['job_apply_link'] ?? $job['job_google_link'] ?? ''),
                description: JobPosting::cleanText($job['job_description'] ?? ''),
                location: collect([$job['job_city'] ?? null, $job['job_state'] ?? null, $job['job_country'] ?? null])->filter()->implode(', ') ?: null,
                countryCode: isset($job['job_country']) ? strtolower($job['job_country']) : $query->countryCode,
                isRemote: (bool) ($job['job_is_remote'] ?? false),
                postedAt: JobPosting::parseDate($job['job_posted_at_datetime_utc'] ?? $job['job_posted_at_timestamp'] ?? null),
                employmentType: $job['job_employment_type'] ?? null,
                salaryMin: JobPosting::money($job['job_min_salary'] ?? null),
                salaryMax: JobPosting::money($job['job_max_salary'] ?? null),
                salaryCurrency: $job['job_salary_currency'] ?? null,
            ))
            ->filter(fn (JobPosting $p) => $p->title !== '' && $p->url !== '')
            ->values()
            ->all();
    }

    private function datePosted(int $days): string
    {
        return match (true) {
            $days <= 1 => 'today',
            $days <= 3 => '3days',
            $days <= 7 => 'week',
            default => 'month',
        };
    }
}
