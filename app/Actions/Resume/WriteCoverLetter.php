<?php

namespace App\Actions\Resume;

use App\Data\CoverLetterData;
use App\Data\JobPostingData;
use App\Data\ResumeData;
use App\Prompts\CoverLetterPrompts;
use App\Services\Ai\Schemas\CoverLetterSchema;
use App\Services\Ai\StructuredCompletion;

class WriteCoverLetter
{
    public function __construct(private readonly StructuredCompletion $completion) {}

    public function handle(
        ResumeData $resume,
        string $jobDescription,
        ?JobPostingData $job = null,
        ?string $company = null,
    ): CoverLetterData {
        $language = $job?->language ?? $resume->language;

        $payload = $this->completion->run(
            task: 'write-cover-letter',
            model: config('resume.models.cover_letter'),
            systemPrompt: CoverLetterPrompts::system($language),
            userPrompt: CoverLetterPrompts::user($resume, $job, $jobDescription, $company),
            schema: CoverLetterSchema::structure(),
        );

        $letter = CoverLetterData::from($payload, $language);

        return $this->withCandidateFacts($letter, $resume, $company ?? $job?->company);
    }

    /**
     * El nombre y el contacto salen del CV, no de lo que el modelo recuerde:
     * el encabezado de la carta tiene que coincidir exacto con el del CV o el
     * reclutador ve dos documentos que no se corresponden.
     */
    private function withCandidateFacts(CoverLetterData $letter, ResumeData $resume, ?string $company): CoverLetterData
    {
        return new CoverLetterData(
            candidateName: $resume->fullName !== '' ? $resume->fullName : $letter->candidateName,
            contact: $resume->contact->isEmpty() ? $letter->contact : $resume->contact,
            role: $letter->role,
            company: $company ?? $letter->company,
            greeting: $letter->greeting,
            paragraphs: $letter->paragraphs,
            closing: $letter->closing,
            language: $letter->language,
        );
    }
}
