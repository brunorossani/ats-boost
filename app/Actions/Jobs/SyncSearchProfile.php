<?php

namespace App\Actions\Jobs;

use App\Actions\Resume\ParseResume;
use App\Jobs\ProcessJobMatch;
use App\JobSearch\JobSearchEngine;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\JobSearchProfile;

/**
 * Busca ofertas nuevas para una búsqueda guardada, las persiste y encola la
 * evaluación + CV adaptado de las que el usuario todavía no vio.
 */
class SyncSearchProfile
{
    public function __construct(
        private readonly JobSearchEngine $engine,
        private readonly ParseResume $parseResume,
    ) {}

    /**
     * @return array{found: int, new_matches: int, providers: array<string, mixed>}
     */
    public function handle(JobSearchProfile $profile): array
    {
        // El CV base se parsea una sola vez por búsqueda, no una vez por oferta.
        if ($profile->hasResume() && $profile->resume_payload === null) {
            $profile->update(['resume_payload' => $this->parseResume->handle($profile->resume_text)->toArray()]);
        }

        $result = $this->engine->search($profile->toQuery());

        // Por huella y no por id: la misma oferta puede volver otro día desde otro portal.
        $known = JobListing::query()
            ->whereIn('id', $profile->matches()->select('job_listing_id'))
            ->pluck('fingerprint')
            ->flip();

        $budget = (int) config('jobsearch.max_new_matches_per_sync', 25);
        $created = [];

        foreach ($result->postings as $posting) {
            if ($known->has($posting->fingerprint()) || count($created) >= $budget) {
                continue;
            }

            $listing = JobListing::fromPosting($posting);

            $created[] = $profile->matches()->create([
                'user_id' => $profile->user_id,
                'job_listing_id' => $listing->id,
                'status' => JobMatch::STATUS_NEW,
            ]);

            $known->put($listing->fingerprint, true);
        }

        $report = [
            'found' => count($result->postings),
            'new_matches' => count($created),
            'providers' => $result->providers,
        ];

        $profile->forceFill(['last_synced_at' => now(), 'last_sync_report' => $report])->save();

        foreach ($created as $match) {
            ProcessJobMatch::dispatch($match->id);
        }

        return $report;
    }
}
