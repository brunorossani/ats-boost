<?php

namespace App\Services\OpenAi\Schemas;

use App\Services\OpenAi\JsonSchema;

final class JobMatchSchema
{
    public static function structure(): JsonSchema
    {
        return JsonSchema::object('job_match', [
            'score' => JsonSchema::integer('Compatibilidad real entre el CV y la oferta, de 0 a 100.'),
            'matching' => JsonSchema::stringArray('Hasta 6 requisitos de la oferta que el CV cumple, en pocas palabras cada uno.'),
            'missing' => JsonSchema::stringArray('Hasta 6 requisitos importantes de la oferta que el CV no muestra. Array vacío si no hay.'),
            'reason' => JsonSchema::string('Una oración, en segunda persona, que resuma el veredicto.'),
        ]);
    }
}
