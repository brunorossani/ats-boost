<?php

namespace App\Jobs;

use App\Actions\Documents\StoreDocument;
use App\Actions\Jobs\ScoreJobMatch;
use App\Actions\Resume\AnalyzeJobPosting;
use App\Actions\Resume\TailorResume;
use App\Enums\DocumentType;
use App\JobSearch\Regions;
use App\Models\JobMatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Evalúa la compatibilidad CV/oferta y, si alcanza el mínimo, genera el CV
 * adaptado con las convenciones del país de la oferta y lo guarda como un
 * Document más del historial.
 */
class ProcessJobMatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * @param  bool  $force  Generar el CV aunque la compatibilidad no llegue al mínimo.
     */
    public function __construct(public int $matchId, public bool $force = false) {}

    public function backoff(): array
    {
        return [60];
    }

    public function handle(
        ScoreJobMatch $score,
        AnalyzeJobPosting $analyzeJob,
        TailorResume $tailor,
        StoreDocument $store,
    ): void {
        $match = JobMatch::with(['listing', 'profile.user'])->find($this->matchId);

        $retryable = [JobMatch::STATUS_NEW, JobMatch::STATUS_PROCESSING, JobMatch::STATUS_FAILED];

        if ($this->force) {
            $retryable[] = JobMatch::STATUS_LOW_MATCH;
        }

        if (! $match || ! in_array($match->status, $retryable, true)) {
            return;
        }

        $resume = $match->profile?->baseResume();

        if (! $resume) {
            $match->update(['status' => JobMatch::STATUS_FAILED, 'error' => 'La búsqueda no tiene un CV base procesado.']);

            return;
        }

        $match->update(['status' => JobMatch::STATUS_PROCESSING, 'error' => null]);

        $listing = $match->listing;
        $jobText = $listing->promptText();

        if ($match->match_score === null) {
            $details = $score->handle($resume, $jobText);
            $match->update(['match_score' => $details['score'], 'match_details' => $details]);
        }

        if (! $this->force && $match->match_score < (int) config('jobsearch.min_match_score', 45)) {
            $match->update(['status' => JobMatch::STATUS_LOW_MATCH]);

            return;
        }

        $job = $analyzeJob->handle($jobText);

        $tailored = $tailor->handle(
            $resume,
            $job,
            $jobText,
            Regions::cvConventions($listing->country_code ?? $match->profile->country_code, $listing->is_remote),
        );

        $document = $store->handle(
            user: $match->profile->user,
            type: DocumentType::TailoredResume,
            data: $tailored,
            role: $job->role ?? $listing->title,
            company: $job->company ?? $listing->company,
            jobDescription: $jobText,
            sourceFilename: $match->profile->resume_filename,
        );

        $match->update(['document_id' => $document->id, 'status' => JobMatch::STATUS_READY]);
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('ProcessJobMatch: falló', ['match_id' => $this->matchId, 'error' => $e?->getMessage()]);

        JobMatch::whereKey($this->matchId)->update([
            'status' => JobMatch::STATUS_FAILED,
            'error' => 'No se pudo generar el CV adaptado. Podés reintentarlo.',
        ]);
    }
}
