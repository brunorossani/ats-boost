<?php

namespace App\JobSearch\Providers;

use App\JobSearch\JobPosting;
use App\JobSearch\JobQuery;
use App\JobSearch\Regions;

/**
 * Get on Board: portal tech de Latinoamérica (Chile, México, Colombia, Perú,
 * Argentina y remoto LatAm). API pública, sin clave.
 */
class GetOnBoardProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'getonbrd';
    }

    public function label(): string
    {
        return 'Get on Board';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supports(JobQuery $query): bool
    {
        return $query->region() === Regions::LATAM || ($query->remoteOnly() && $query->includeRemote);
    }

    public function search(JobQuery $query): array
    {
        $response = $this->ensureOk(
            $this->http()->get('https://www.getonbrd.com/api/v0/search/jobs', [
                'query' => $query->keywords,
                'per_page' => min(100, $query->limit),
                'page' => 1,
                'expand' => '["company"]',
            ])
        );

        return collect($response->json('data', []))
            ->map(function (array $job) {
                $a = $job['attributes'] ?? [];
                $countries = (array) ($a['countries'] ?? []);
                $remote = (bool) ($a['remote'] ?? false) || in_array($a['remote_modality'] ?? null, ['fully_remote', 'remote_local'], true);
                $countryCode = collect($countries)->map(fn ($c) => Regions::detectCountry($c))->filter()->first();

                return new JobPosting(
                    source: $this->key(),
                    externalId: (string) ($job['id'] ?? ''),
                    title: (string) ($a['title'] ?? ''),
                    company: (string) (data_get($a, 'company.data.attributes.name') ?? data_get($a, 'company.data.id') ?? ''),
                    url: (string) (data_get($job, 'links.public_url') ?? ''),
                    description: JobPosting::cleanText(implode("\n\n", array_filter([
                        $a['description'] ?? null,
                        $a['functions'] ?? null,
                        $a['desirable'] ?? null,
                        $a['benefits'] ?? null,
                    ]))),
                    location: implode(', ', $countries) ?: null,
                    countryCode: $countryCode,
                    isRemote: $remote,
                    remoteZone: $remote ? ($a['remote_zone'] ?? 'LATAM') : null,
                    postedAt: JobPosting::parseDate($a['published_at'] ?? null),
                    employmentType: data_get($a, 'modality.data.id'),
                    salaryMin: JobPosting::money($a['min_salary'] ?? null),
                    salaryMax: JobPosting::money($a['max_salary'] ?? null),
                    salaryCurrency: isset($a['min_salary']) ? 'USD' : null,
                );
            })
            ->filter(fn (JobPosting $p) => $p->externalId !== '' && $p->title !== '' && $p->url !== '')
            ->values()
            ->all();
    }
}
