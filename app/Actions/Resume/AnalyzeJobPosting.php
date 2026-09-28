<?php

namespace App\Actions\Resume;

use App\Data\JobPostingData;
use App\Enums\Language;
use App\Prompts\JobPostingPrompts;
use App\Services\Ai\Schemas\JobPostingSchema;
use App\Services\Ai\StructuredCompletion;

/**
 * Oferta de trabajo → puesto, empresa, palabras clave y requisitos.
 *
 * El idioma de la oferta manda sobre el del CV: si alguien con un CV en
 * español se postula a una búsqueda en inglés, el CV adaptado sale en inglés.
 */
class AnalyzeJobPosting
{
    public function __construct(private readonly StructuredCompletion $completion) {}

    public function handle(string $jobDescription): JobPostingData
    {
        $language = Language::detect($jobDescription);

        $payload = $this->completion->run(
            task: 'analyze-job-posting',
            model: config('resume.models.parsing'),
            systemPrompt: JobPostingPrompts::system($language),
            userPrompt: JobPostingPrompts::user($jobDescription),
            schema: JobPostingSchema::structure(),
        );

        return JobPostingData::from($payload, $language);
    }
}
