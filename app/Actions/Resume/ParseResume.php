<?php

namespace App\Actions\Resume;

use App\Data\ResumeData;
use App\Enums\Language;
use App\Prompts\ResumePrompts;
use App\Services\Ai\Schemas\ResumeSchema;
use App\Services\Ai\StructuredCompletion;

/**
 * Texto plano de un CV → currículum estructurado.
 *
 * Sustituye a las tres llamadas sueltas del motor anterior (nombre, línea de
 * contacto y cuerpo en HTML) por una sola que devuelve el documento entero.
 */
class ParseResume
{
    public function __construct(private readonly StructuredCompletion $completion) {}

    public function handle(string $resumeText, ?Language $language = null): ResumeData
    {
        // Sin idioma explícito se usa el del propio CV: al analizar o mejorar
        // un currículum no hay oferta que imponga otro.
        $language ??= Language::detect($resumeText);

        $payload = $this->completion->run(
            task: 'parse-resume',
            model: config('resume.models.parsing'),
            systemPrompt: ResumePrompts::parseSystem($language),
            userPrompt: ResumePrompts::parseUser($resumeText),
            schema: ResumeSchema::structure(),
        );

        return ResumeData::from($payload, $language);
    }
}
