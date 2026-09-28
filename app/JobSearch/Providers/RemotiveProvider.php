<?php

namespace App\JobSearch\Providers;

use App\JobSearch\JobPosting;
use App\JobSearch\JobQuery;

/**
 * Remotive: trabajo 100% remoto internacional. API pública, sin clave.
 * Cada oferta indica desde qué zonas acepta candidatos.
 */
class RemotiveProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'remotive';
    }

    public function label(): string
    {
        return 'Remotive';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supports(JobQuery $query): bool
    {
        return $query->includeRemote;
    }

    public function search(JobQuery $query): array
    {
        $response = $this->ensureOk(
            $this->http()->get('https://remotive.com/api/remote-jobs', [
                'search' => $query->keywords,
                'limit' => min(100, $query->limit),
            ])
        );

        return collect($response->json('jobs', []))
            ->map(fn (array $job) => new JobPosting(
                source: $this->key(),
                externalId: (string) ($job['id'] ?? md5($job['url'] ?? '')),
                title: (string) ($job['title'] ?? ''),
                company: (string) ($job['company_name'] ?? ''),
                url: (string) ($job['url'] ?? ''),
                description: JobPosting::cleanText($job['description'] ?? ''),
                location: $job['candidate_required_location'] ?? null,
                isRemote: true,
                remoteZone: $job['candidate_required_location'] ?? 'Worldwide',
                postedAt: JobPosting::parseDate($job['publication_date'] ?? null),
                employmentType: $job['job_type'] ?? null,
            ))
            ->filter(fn (JobPosting $p) => $p->title !== '' && $p->url !== '')
            ->values()
            ->all();
    }
}
