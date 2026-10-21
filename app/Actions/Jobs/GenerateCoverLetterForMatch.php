<?php

namespace App\Actions\Jobs;

use App\Actions\Documents\StoreDocument;
use App\Actions\Resume\AnalyzeJobPosting;
use App\Actions\Resume\ParseResume;
use App\Actions\Resume\WriteCoverLetter;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\JobMatch;

/**
 * Genera una carta de presentación para un JobMatch específico,
 * usando el CV base de la búsqueda y la descripción de la oferta.
 */
class GenerateCoverLetterForMatch
{
    public function __construct(
        private readonly ParseResume $parseResume,
        private readonly AnalyzeJobPosting $analyzeJob,
        private readonly WriteCoverLetter $writeCoverLetter,
        private readonly StoreDocument $store,
    ) {}

    public function handle(JobMatch $match): Document
    {
        $profile = $match->profile;
        $listing = $match->listing;

        // Analizar la oferta
        $job = $this->analyzeJob->handle($listing->description);

        // Parsear el CV base
        $resume = $this->parseResume->handle($profile->resume_text, $job->language);

        // Generar la carta
        $letter = $this->writeCoverLetter->handle(
            $resume,
            $listing->description,
            $job,
            $listing->company
        );

        // Guardar como documento
        return $this->store->handle(
            user: $match->user,
            type: DocumentType::CoverLetter,
            data: $letter,
            role: $job->role,
            company: $listing->company,
            jobDescription: $listing->description,
            sourceFilename: $profile->resume_filename ?? 'CV base',
        );
    }
}
