<?php

namespace App\Actions\Jobs;

use App\Data\Cast;
use App\Data\ResumeData;
use App\Prompts\JobMatchPrompts;
use App\Services\OpenAi\Schemas\JobMatchSchema;
use App\Services\OpenAi\StructuredCompletion;

/**
 * CV estructurado + oferta → puntaje de compatibilidad con su justificación.
 */
class ScoreJobMatch
{
    public function __construct(private readonly StructuredCompletion $completion) {}

    /**
     * @return array{score: int, matching: list<string>, missing: list<string>, reason: string}
     */
    public function handle(ResumeData $resume, string $jobText): array
    {
        $payload = $this->completion->run(
            task: 'score-job-match',
            model: config('resume.models.analysis'),
            systemPrompt: JobMatchPrompts::system(),
            userPrompt: JobMatchPrompts::user($resume, $jobText),
            schema: JobMatchSchema::structure(),
            temperature: 0.0,
        );

        return [
            'score' => max(0, min(100, (int) ($payload['score'] ?? 0))),
            'matching' => array_slice(Cast::stringList($payload['matching'] ?? []), 0, 6),
            'missing' => array_slice(Cast::stringList($payload['missing'] ?? []), 0, 6),
            'reason' => Cast::string($payload['reason'] ?? ''),
        ];
    }
}
