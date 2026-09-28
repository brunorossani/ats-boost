<?php

namespace App\Services\Ai\Schemas;

use App\Services\Ai\JsonSchema;

final class AtsReportSchema
{
    public static function structure(): JsonSchema
    {
        return JsonSchema::object('ats_report', [
            'score' => JsonSchema::integer('Puntaje global de compatibilidad con ATS, de 0 a 100.'),
            'breakdown' => JsonSchema::shape([
                'structure' => JsonSchema::integer('Claridad de secciones y jerarquía, de 0 a 100.'),
                'parsability' => JsonSchema::integer('Facilidad de lectura automática: sin columnas, tablas complejas ni gráficos, de 0 a 100.'),
                'content' => JsonSchema::integer('Calidad de la redacción: verbos de acción y logros medibles, de 0 a 100.'),
                'keywords' => JsonSchema::integer('Densidad de términos técnicos relevantes para el perfil, de 0 a 100.'),
            ], 'Desglose del puntaje por dimensión.'),
            'strengths' => JsonSchema::stringArray('Entre 2 y 4 fortalezas concretas del CV, en segunda persona.'),
            'issues' => JsonSchema::array(
                'Entre 2 y 5 problemas concretos y accionables. Nunca devuelvas un array vacío salvo que el CV sea impecable.',
                JsonSchema::shape([
                    'title' => JsonSchema::string('El problema en menos de 8 palabras.'),
                    'detail' => JsonSchema::string('Cómo corregirlo, en una o dos oraciones y en segunda persona.'),
                    'severity' => JsonSchema::enum('Impacto sobre el puntaje.', ['high', 'medium', 'low']),
                ]),
            ),
            'missing_keywords' => JsonSchema::stringArray(
                'Términos que el perfil sugiere pero el CV no menciona. Array vacío si no aplica.'
            ),
        ]);
    }
}
