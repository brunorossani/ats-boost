<?php

namespace App\Services\Ai\Schemas;

use App\Services\Ai\JsonSchema;

final class JobPostingSchema
{
    public static function structure(): JsonSchema
    {
        return JsonSchema::object('job_posting', [
            'role' => JsonSchema::nullableString('Título del puesto, sin la empresa ni el nivel de seniority.'),
            'company' => JsonSchema::nullableString('Nombre de la empresa que publica la oferta.'),
            'seniority' => JsonSchema::nullableString('Nivel del puesto: junior, semi senior, senior, lead u otro.'),
            'keywords' => JsonSchema::stringArray(
                'Tecnologías, herramientas y términos exactos que un ATS buscaría. Entre 8 y 20, tal como aparecen escritos en la oferta.'
            ),
            'requirements' => JsonSchema::stringArray(
                'Requisitos excluyentes y deseables, uno por elemento, redactados en forma breve.'
            ),
            'responsibilities' => JsonSchema::stringArray(
                'Responsabilidades principales del puesto, una por elemento.'
            ),
        ]);
    }
}
