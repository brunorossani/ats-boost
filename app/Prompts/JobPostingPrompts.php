<?php

namespace App\Prompts;

use App\Enums\Language;

final class JobPostingPrompts
{
    public static function system(Language $language): string
    {
        return <<<TXT
        Sos un analizador de ofertas laborales. Extraés de forma literal lo que la
        oferta pide, sin interpretarlo ni ampliarlo.

        REGLAS:
        - Copiá las tecnologías y herramientas tal como están escritas en la oferta.
          Si dice "Vue.js" no escribas "Vue"; si dice "SQL" no escribas "MySQL".
        - No agregues requisitos habituales del rubro que la oferta no menciona.
        - Si la oferta no dice la empresa o el nivel, devolvé una cadena vacía.
        - Escribí requisitos y responsabilidades en {$language->promptName()}.
        - Las palabras clave se dejan en el idioma original de la oferta, porque un ATS
          busca coincidencias exactas.
        TXT;
    }

    public static function user(string $jobDescription): string
    {
        return "--- OFERTA DE TRABAJO ---\n{$jobDescription}";
    }
}
