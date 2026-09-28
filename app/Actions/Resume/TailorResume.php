<?php

namespace App\Actions\Resume;

use App\Data\ExperienceData;
use App\Data\JobPostingData;
use App\Data\ResumeData;
use App\Prompts\ResumePrompts;
use App\Services\OpenAi\Schemas\ResumeSchema;
use App\Services\OpenAi\StructuredCompletion;

/**
 * Currículum estructurado + oferta → currículum adaptado.
 *
 * Entra y sale el mismo tipo, así que la adaptación se puede encadenar o
 * repetir sobre otra oferta sin volver a parsear el PDF.
 */
class TailorResume
{
    public function __construct(private readonly StructuredCompletion $completion) {}

    /**
     * @param  string|null  $conventions  Convenciones de CV del país del puesto (ver Regions::cvConventions).
     */
    public function handle(ResumeData $resume, JobPostingData $job, string $jobDescription, ?string $conventions = null): ResumeData
    {
        $payload = $this->completion->run(
            task: 'tailor-resume',
            model: config('resume.models.tailoring'),
            systemPrompt: ResumePrompts::tailorSystem($job->language, $conventions),
            userPrompt: ResumePrompts::tailorUser($resume, $job, $jobDescription),
            schema: ResumeSchema::structure(),
            // Algo de temperatura: con 0 el modelo copia las viñetas originales
            // en lugar de reescribirlas con el vocabulario de la oferta.
            temperature: 0.3,
        );

        return $this->capBullets(ResumeData::from($payload, $job->language));
    }

    /**
     * El esquema no puede expresar "máximo N viñetas", así que el límite se
     * aplica acá en vez de confiar en que el prompt se respete.
     */
    private function capBullets(ResumeData $resume): ResumeData
    {
        $max = (int) config('resume.limits.bullets_per_entry', 5);

        $experience = array_map(
            fn (ExperienceData $entry): ExperienceData => count($entry->bullets) <= $max
                ? $entry
                : new ExperienceData(
                    role: $entry->role,
                    company: $entry->company,
                    location: $entry->location,
                    dates: $entry->dates,
                    bullets: array_slice($entry->bullets, 0, $max),
                ),
            $resume->experience,
        );

        return new ResumeData(
            fullName: $resume->fullName,
            headline: $resume->headline,
            contact: $resume->contact,
            summary: $resume->summary,
            experience: $experience,
            education: $resume->education,
            projects: $resume->projects,
            skills: $resume->skills,
            certifications: $resume->certifications,
            language: $resume->language,
        );
    }
}
