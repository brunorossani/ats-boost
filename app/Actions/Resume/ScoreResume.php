<?php

namespace App\Actions\Resume;

use App\Data\AtsReportData;
use App\Enums\Language;
use App\Prompts\AtsPrompts;
use App\Services\Ai\Schemas\AtsReportSchema;
use App\Services\Ai\StructuredCompletion;

/**
 * Puntúa un CV contra criterios ATS y devuelve el desglose.
 *
 * Recibe texto plano, no el CV estructurado: hay que evaluar el documento tal
 * como lo va a leer un ATS, con sus problemas de formato incluidos. Si se
 * puntuara la versión ya estructurada, el puntaje mediría el trabajo del
 * parser en vez del CV del usuario.
 */
class ScoreResume
{
    public function __construct(private readonly StructuredCompletion $completion) {}

    public function handle(string $resumeText, ?string $jobDescription = null): AtsReportData
    {
        $language = Language::detect($resumeText);

        $payload = $this->completion->run(
            task: 'score-resume',
            model: config('resume.models.analysis'),
            systemPrompt: AtsPrompts::system($language),
            userPrompt: AtsPrompts::user($resumeText, $jobDescription),
            schema: AtsReportSchema::structure(),
        );

        return AtsReportData::from($payload);
    }
}
