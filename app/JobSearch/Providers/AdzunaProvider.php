<?php

namespace App\JobSearch\Providers;

use App\JobSearch\JobPosting;
use App\JobSearch\JobQuery;

/**
 * Adzuna: fuerte en Reino Unido, Europa occidental, EE.UU., Canadá, Australia,
 * Brasil y México. No cubre el resto de LatAm. Su descripción es un extracto.
 */
class AdzunaProvider extends AbstractProvider
{
    public const COUNTRIES = ['gb', 'us', 'ca', 'au', 'nz', 'de', 'fr', 'es', 'it', 'nl', 'pl', 'at', 'be', 'ch', 'br', 'mx', 'in', 'sg', 'za'];

    public function key(): string
    {
        return 'adzuna';
    }

    public function label(): string
    {
        return 'Adzuna';
    }

    public function isConfigured(): bool
    {
        return filled($this->config('app_id')) && filled($this->config('app_key'));
    }

    public function supports(JobQuery $query): bool
    {
        return $query->countryCode !== null && in_array(strtolower($query->countryCode), self::COUNTRIES, true);
    }

    public function search(JobQuery $query): array
    {
        $country = strtolower($query->countryCode);

        $params = array_filter([
            'app_id' => $this->config('app_id'),
            'app_key' => $this->config('app_key'),
            'what' => $query->keywords,
            'where' => $query->city,
            'results_per_page' => min(50, $query->limit),
            'max_days_old' => max(1, $query->maxAgeDays),
            'sort_by' => 'date',
            'content-type' => 'application/json',
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->ensureOk(
            $this->http()->get("https://api.adzuna.com/v1/api/jobs/{$country}/search/1", $params)
        );

        return collect($response->json('results', []))
            ->map(function (array $job) use ($country) {
                $description = JobPosting::cleanText($job['description'] ?? '');

                return new JobPosting(
                    source: $this->key(),
                    externalId: (string) ($job['id'] ?? md5($job['redirect_url'] ?? '')),
                    title: JobPosting::cleanText($job['title'] ?? ''),
                    company: (string) data_get($job, 'company.display_name', ''),
                    url: (string) ($job['redirect_url'] ?? ''),
                    description: $description,
                    location: data_get($job, 'location.display_name'),
                    countryCode: $country,
                    isRemote: (bool) preg_match('/\b(remote|remoto|home office|teletrabajo)\b/i', ($job['title'] ?? '').' '.$description),
                    postedAt: JobPosting::parseDate($job['created'] ?? null),
                    employmentType: $job['contract_time'] ?? $job['contract_type'] ?? null,
                    salaryMin: JobPosting::money($job['salary_min'] ?? null),
                    salaryMax: JobPosting::money($job['salary_max'] ?? null),
                );
            })
            ->filter(fn (JobPosting $p) => $p->title !== '' && $p->url !== '')
            ->values()
            ->all();
    }
}
