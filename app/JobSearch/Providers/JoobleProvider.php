<?php

namespace App\JobSearch\Providers;

use App\JobSearch\JobPosting;
use App\JobSearch\JobQuery;

/**
 * Jooble: agregador con presencia en ~70 países, incluida toda LatAm.
 * La descripción que devuelve es un extracto.
 */
class JoobleProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'jooble';
    }

    public function label(): string
    {
        return 'Jooble';
    }

    public function isConfigured(): bool
    {
        return filled($this->config('api_key'));
    }

    public function supports(JobQuery $query): bool
    {
        return $query->countryCode !== null;
    }

    public function search(JobQuery $query): array
    {
        $response = $this->ensureOk(
            $this->http()->post('https://jooble.org/api/'.$this->config('api_key'), [
                'keywords' => $query->keywords,
                'location' => $query->locationText(),
                'page' => '1',
                'ResultOnPage' => (string) min(50, $query->limit),
                'datecreatedfrom' => now()->subDays(max(1, $query->maxAgeDays))->toDateString(),
            ])
        );

        return collect($response->json('jobs', []))
            ->map(fn (array $job) => new JobPosting(
                source: $this->key(),
                externalId: (string) ($job['id'] ?? md5($job['link'] ?? '')),
                title: JobPosting::cleanText($job['title'] ?? ''),
                company: (string) ($job['company'] ?? ''),
                url: (string) ($job['link'] ?? ''),
                description: JobPosting::cleanText($job['snippet'] ?? ''),
                location: $job['location'] ?? null,
                countryCode: $query->countryCode,
                isRemote: (bool) preg_match('/\b(remote|remoto|home office|teletrabajo)\b/i', ($job['title'] ?? '').' '.($job['location'] ?? '')),
                postedAt: JobPosting::parseDate($job['updated'] ?? null),
                employmentType: $job['type'] ?? null,
            ))
            ->filter(fn (JobPosting $p) => $p->title !== '' && $p->url !== '')
            ->values()
            ->all();
    }
}
