<?php

namespace App\Prompts;

use App\Data\ResumeData;

final class JobMatchPrompts
{
    /**
     * Filtro previo a la adaptación: una oferta que no encaja no vale el costo
     * de generar un CV, y tampoco le sirve al candidato verla primera.
     */
    public static function system(): string
    {
        return <<<'TXT'
        Sos un reclutador técnico. Compará el CV del candidato con la oferta y evaluá
        qué tan probable es que pase el primer filtro.

        CÓMO PUNTUAR:
        - 80–100: cumple el rol, el nivel y casi todos los requisitos excluyentes.
        - 60–79: cumple el rol y la mayoría de los requisitos; le faltan deseables.
        - 45–59: encaje parcial; le falta algún requisito importante.
        - 0–44: otro rol, otro nivel o le faltan varios excluyentes (idioma, años, tecnología central, ubicación o permisos).

        HONESTIDAD:
        - No inflés el puntaje. Un requisito que el CV no muestra cuenta como faltante.
        - Escribí la salida en español, dirigiéndote al candidato de vos.
        TXT;
    }

    public static function user(ResumeData $resume, string $jobText): string
    {
        $structured = json_encode($resume->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<TXT
        --- CV ESTRUCTURADO DEL CANDIDATO ---
        {$structured}

        --- OFERTA ---
        {$jobText}
        TXT;
    }
}
