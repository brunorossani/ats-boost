<?php

namespace App\Actions\Resume;

use App\Data\ResumeData;
use App\Prompts\ResumePrompts;
use App\Services\Ai\Schemas\ResumeSchema;
use App\Services\Ai\StructuredCompletion;

/**
 * Reescribe un currículum sin apuntarlo a ninguna oferta.
 *
 * Es lo que se ofrece después del análisis ATS: el usuario ve el puntaje y
 * los problemas concretos, y desde ahí genera la versión corregida.
 */
class ImproveResume
{
    public function __construct(private readonly StructuredCompletion $completion) {}

    public function handle(ResumeData $resume): ResumeData
    {
        $payload = $this->completion->run(
            task: 'improve-resume',
            model: config('resume.models.tailoring'),
            systemPrompt: ResumePrompts::improveSystem($resume->language),
            userPrompt: ResumePrompts::improveUser($resume),
            schema: ResumeSchema::structure(),
        );

        return ResumeData::from($payload, $resume->language);
    }
}
